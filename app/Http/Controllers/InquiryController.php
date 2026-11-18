<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class InquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'phone' => ['required', 'string', 'max:40', 'regex:/^\+?[\d\s()\-]{10,25}$/'],
            'email' => 'nullable|email|max:254', 'company' => 'nullable|string|max:200',
            'message' => 'nullable|string|max:5000', 'consent' => 'required|accepted',
            'items' => 'present|array|max:50',
            'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'website' => 'nullable|string|max:0',
        ], [
            'name.required' => 'Укажите ваше имя.',
            'name.min' => 'Имя должно содержать не менее двух символов.',
            'phone.required' => 'Укажите номер телефона.',
            'phone.regex' => 'Проверьте номер телефона, например +7 (707) 123-45-67.',
            'email.email' => 'Проверьте адрес электронной почты.',
            'consent.accepted' => 'Для отправки заявки необходимо согласие на обработку данных.',
            'items.*.quantity.min' => 'Количество товара должно быть не меньше одного.',
            'items.*.quantity.max' => 'Для тиража более 100 000 штук свяжитесь с нами по телефону.',
            'items.*.id.distinct' => 'В подборке есть повторяющиеся товары.',
            'website.max' => 'Не удалось отправить заявку. Свяжитесь с нами по телефону.',
        ]);
        $products = Product::where('active', true)->whereIn('id', array_column($data['items'], 'id'))->get()->keyBy('id');
        if ($products->count() !== count($data['items'])) {
            return response()->json(['message' => 'Один из выбранных товаров больше недоступен. Обновите подборку.'], 422);
        }
        $items = array_map(fn ($item) => [
            'id' => $item['id'], 'quantity' => $item['quantity'],
            'name' => $products[$item['id']]->name,
            'article' => $products[$item['id']]->article,
            'provider' => $products[$item['id']]->provider,
            'price' => $products[$item['id']]->price,
            'currency' => $products[$item['id']]->currency,
        ], $data['items']);
        $id = (string) Str::uuid();
        DB::table('inquiries')->insert([
            'id' => $id, 'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null,
            'company' => $data['company'] ?? null, 'message' => $data['message'] ?? null,
            'items' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'consented_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->sendNotification($id, $data, $items);

        return response()->json(['id' => $id, 'message' => 'Заявка сохранена. Мы свяжемся с вами, чтобы уточнить детали.'], 201);
    }

    private function sendNotification(string $id, array $data, array $items): void
    {
        $recipient = config('mail.inquiry_recipient');
        if (! $recipient) {
            return;
        }

        $number = strtoupper(substr($id, 0, 8));
        $lines = [
            "Заявка MEDIAPRO №$number",
            "Имя: {$data['name']}",
            "Телефон: {$data['phone']}",
            'Email: '.($data['email'] ?? '—'),
            'Компания: '.($data['company'] ?? '—'),
            'Комментарий: '.($data['message'] ?? '—'),
            '',
            $items ? 'Товары:' : 'Заявка без товаров.',
        ];
        foreach ($items as $item) {
            $lines[] = "{$item['name']} (арт. {$item['article']}, {$item['provider']}) — {$item['quantity']} шт.";
        }

        try {
            Mail::raw(implode("\n", $lines), function (Message $message) use ($recipient, $number, $data): void {
                $message->to($recipient)->subject("Новая заявка MEDIAPRO №$number");
                if (! empty($data['email'])) {
                    $message->replyTo($data['email']);
                }
            });
        } catch (Throwable $exception) {
            Log::warning('Inquiry email delivery failed.', [
                'inquiry_id' => $id,
                'error_type' => $exception::class,
            ]);
        }
    }
}
