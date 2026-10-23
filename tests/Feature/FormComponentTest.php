<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Phase 02 requires forms to have clear validation/error states. These tests
 * pin the accessibility contract between the input and its error message.
 */
class FormComponentTest extends TestCase
{
    /**
     * Share a populated error bag with the view, mirroring what Laravel does
     * after a failed validation redirect.
     */
    private function withErrors(array $errors): void
    {
        $bag = new ViewErrorBag;
        $bag->put('default', new MessageBag($errors));

        view()->share('errors', $bag);
    }

    private function withoutErrors(): void
    {
        view()->share('errors', new ViewErrorBag);
    }

    public function test_input_is_clean_when_there_are_no_errors(): void
    {
        $this->withoutErrors();

        $html = Blade::render('<x-input name="email" type="email" />');

        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('id="email"', $html);
        $this->assertStringNotContainsString('aria-invalid', $html);
    }

    public function test_input_is_marked_invalid_and_linked_to_its_error_message(): void
    {
        $this->withErrors(['email' => 'Email wajib diisi.']);

        $input = Blade::render('<x-input name="email" type="email" />');

        $this->assertStringContainsString('aria-invalid="true"', $input);
        $this->assertStringContainsString('aria-describedby="email-error"', $input);

        // The describedby target must actually exist in the error component.
        $error = Blade::render('<x-field-error name="email" />');
        $this->assertStringContainsString('id="email-error"', $error);
        $this->assertStringContainsString('Email wajib diisi.', $error);

        $this->withoutErrors();
    }

    public function test_field_error_renders_nothing_without_an_error(): void
    {
        $this->withoutErrors();

        $html = Blade::render('<x-field-error name="email" />');

        $this->assertSame('', trim($html));
    }

    public function test_label_marks_required_fields(): void
    {
        $this->withoutErrors();

        $required = Blade::render('<x-label for="email" :required="true">Email</x-label>');
        $this->assertStringContainsString('label-required', $required);
        $this->assertStringContainsString('for="email"', $required);

        $optional = Blade::render('<x-label for="note">Catatan</x-label>');
        $this->assertStringNotContainsString('label-required', $optional);
    }

    public function test_login_form_exposes_validation_errors_accessibly(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => '',
            'password' => '',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);

        $page = $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), ['email' => '', 'password' => '']);

        $page->assertOk();
        $page->assertSee('aria-invalid="true"', escape: false);
        $page->assertSee('id="email-error"', escape: false);
    }
}
