<?php

declare(strict_types=1);

namespace BloodMatch\Controllers\Auth;

use BloodMatch\Services\AuthService;
use BloodMatch\Repositories\Exceptions\DuplicateEntryException;
use BloodMatch\Services\Exceptions\ValidationException;
use BloodMatch\Utils\Response;

final class RegisterController
{
    public function register(): void
    {
        $service = new AuthService();

        $input = !empty($_POST) ? $_POST : \BloodMatch\Utils\Request::json();
        $file = isset($_FILES['national_id']) && is_array($_FILES['national_id']) ? $_FILES['national_id'] : null;

        try {
            $user = $service->register($input, $file);
        } catch (DuplicateEntryException $e) {
            Response::error($e->getMessage(), 409, ['email' => [$e->getMessage()]]);
            return;
        } catch (ValidationException $e) {
            Response::error($e->getMessage(), 400, $e->errors());
            return;
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 400);
            return;
        }

        Response::success(['user' => $user], 201);
    }
}
