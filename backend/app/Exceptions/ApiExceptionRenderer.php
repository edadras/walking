<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Renders every API error in the single structured shape documented in docs/phase-0/04-api.md. */
class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        [$status, $code, $message, $fields, $context] = $this->describe($e);

        $error = array_filter([
            'code' => $code,
            'message' => $message,
            'fields' => $fields,
            'context' => $context ?: null,
            'request_id' => $request->attributes->get('request_id'),
        ], fn ($v) => $v !== null);

        if (config('app.debug') && $status >= 500) {
            $error['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
        }

        $response = new JsonResponse(['error' => $error], $status);

        if ($e instanceof ThrottleRequestsException) {
            $response->headers->add($e->getHeaders());
        }

        return $response;
    }

    /** @return array{0:int,1:string,2:string,3:?array,4:array} */
    private function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), null, $e->context],
            $e instanceof ValidationException => [422, 'validation_failed', $e->validator->errors()->first() ?: 'اطلاعات ارسال‌شده معتبر نیست.', $e->errors(), []],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'لطفاً دوباره وارد حساب خود شوید.', null, []],
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => [403, 'forbidden', 'اجازه انجام این کار را ندارید.', null, []],
            $e instanceof ThrottleRequestsException => [429, 'too_many_requests', 'تعداد درخواست‌ها زیاد است. کمی بعد دوباره تلاش کنید.', null, ['retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60)]],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', 'مورد درخواستی پیدا نشد.', null, []],
            $e instanceof MethodNotAllowedHttpException => [405, 'method_not_allowed', 'این درخواست پشتیبانی نمی‌شود.', null, []],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', 'درخواست قابل انجام نیست.', null, []],
            default => [500, 'server_error', 'در سرور مشکلی پیش آمد. لطفاً دوباره تلاش کنید.', null, []],
        };
    }
}
