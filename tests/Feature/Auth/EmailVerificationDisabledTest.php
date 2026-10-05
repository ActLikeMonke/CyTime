<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Fortify\Features;
use Tests\TestCase;

class EmailVerificationDisabledTest extends TestCase
{
    public function test_email_verification_is_disabled_for_users(): void
    {
        $this->assertFalse(in_array(Features::emailVerification(), config('fortify.features', []), true));
        $this->assertFalse(in_array(MustVerifyEmail::class, class_implements(User::class), true));
    }
}
