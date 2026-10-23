<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\ContactRequest;
use App\Mail\ContactMail;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class PublicController extends Controller
{
    use ApiResponse;

    private const ALLOWED_PAGES = [
        'terms', 'privacy', 'accessibility',
    ];

    public function contact(ContactRequest $request): JsonResponse
    {
        Mail::to('support@' . config('platform.company_url'))
            ->send(new ContactMail($request->validated()));

        return $this->success('Message received. We will get back to you shortly.');
    }

    public function page(string $page): JsonResponse
    {
        if (! in_array($page, self::ALLOWED_PAGES, true)) {
            return $this->notFound('Page not found.');
        }

        return $this->success('Page metadata.', [
            'page' => [
                'slug'    => $page,
                'title'   => ucwords(str_replace('-', ' ', $page)),
                'company' => config('platform.company_full_name'),
            ],
        ]);
    }
}
