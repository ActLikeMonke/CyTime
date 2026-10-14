<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class UiComponentsTest extends TestCase
{
    public function test_button_renders_variant_and_passthrough_attributes(): void
    {
        $html = Blade::render('<x-ui.button variant="primary" id="save-action" type="submit">Save</x-ui.button>');

        $this->assertStringContainsString('ui-button--primary', $html);
        $this->assertStringContainsString('id="save-action"', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('Save', $html);
    }

    public function test_input_renders_label_and_validation_error_accessibly(): void
    {
        $html = Blade::render('<x-ui.input name="email" label="Email" error="Enter a valid email address." />');

        $this->assertStringContainsString('class="ui-field__label"', $html);
        $this->assertStringContainsString('for="', $html);
        $this->assertStringContainsString('class="ui-input"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('class="ui-field__error" role="alert"', $html);
        $this->assertStringContainsString('Enter a valid email address.', $html);
        $this->assertStringNotContainsString('ui-input-wrap', $html);
    }

    public function test_date_and_month_inputs_render_an_accessible_picker_toggle(): void
    {
        $date = Blade::render('<x-ui.input id="from" label="From" type="date" max="2026-10-06" />');
        $month = Blade::render('<x-ui.input id="period" label="Month" type="month" />');
        $text = Blade::render('<x-ui.input id="name" label="Name" type="text" />');

        $this->assertStringContainsString('ui-input-wrap--picker', $date);
        $this->assertStringContainsString('data-date-picker-toggle="date"', $date);
        $this->assertStringContainsString('aria-controls="from"', $date);
        $this->assertStringContainsString('aria-label="Choose date"', $date);
        $this->assertStringContainsString('aria-expanded="false"', $date);
        $this->assertStringContainsString('max="2026-10-06"', $date);
        $this->assertStringContainsString('data-date-picker-toggle="month"', $month);
        $this->assertStringContainsString('aria-label="Choose month"', $month);
        $this->assertStringNotContainsString('data-date-picker-toggle', $text);
    }

    public function test_alert_badge_card_and_modal_render_semantic_variants(): void
    {
        $alert = Blade::render('<x-ui.alert type="error">Unable to save.</x-ui.alert>');
        $badge = Blade::render('<x-ui.badge variant="success">Approved</x-ui.badge>');
        $card = Blade::render('<x-ui.card id="summary-card">Summary</x-ui.card>');
        $modal = Blade::render('<x-ui.modal name="confirm-dialog" title="Confirm" class="custom-modal">Continue?</x-ui.modal>');

        $this->assertStringContainsString('role="alert"', $alert);
        $this->assertStringContainsString('ui-alert--error', $alert);
        $this->assertStringContainsString('ui-badge--success', $badge);
        $this->assertStringContainsString('id="summary-card"', $card);
        $this->assertStringContainsString('<dialog', $modal);
        $this->assertStringContainsString('class="ui-modal custom-modal"', $modal);
        $this->assertStringContainsString('aria-labelledby="confirm-dialog-title"', $modal);
        $this->assertStringContainsString('Continue?', $modal);
    }

    public function test_dropdown_forwards_attributes_and_contains_its_slot(): void
    {
        $html = Blade::render('<x-ui.dropdown id="account-menu"><button>Account</button></x-ui.dropdown>');

        $this->assertStringContainsString('class="ui-dropdown"', $html);
        $this->assertStringContainsString('data-dropdown=""', $html);
        $this->assertStringContainsString('id="account-menu"', $html);
        $this->assertStringContainsString('<button>Account</button>', $html);
    }
}
