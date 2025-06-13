<?php
namespace Core\Services\Auth;

use Core\Services\Http\Request;

interface GuardInterface
{
    public function authorize(object $user): void;
    public function token(): ?string;
    public function payload(): ?object;
    public function getJti(): ?string;
    public function revokeToken(string $jti): void;

}
