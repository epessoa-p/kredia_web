<?php

namespace Tests\Feature\Security;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_removes_scripts_handlers_and_javascript_urls(): void
    {
        $dirty = '<p onclick="x()">Hola {{cliente_nombre}} ñ</p><script>alert(1)</script>'
            . '<img src="a.png" onerror="alert(1)"><a href="javascript:alert(1)">x</a><iframe src="//evil"></iframe>';

        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringContainsString('Hola {{cliente_nombre}} ñ', $clean);
        $this->assertStringContainsString('<img src="a.png">', $clean);
    }

    public function test_keeps_formatting_tables_and_signature_images(): void
    {
        $html = '<table style="width:100%"><tr><td><strong>Monto</strong></td></tr></table>'
            . '<img src="data:image/png;base64,AAAA" alt="Firma">';

        $clean = HtmlSanitizer::clean($html);

        $this->assertStringContainsString('<table style="width:100%">', $clean);
        $this->assertStringContainsString('data:image/png;base64,AAAA', $clean);
    }

    public function test_blocks_non_image_data_urls(): void
    {
        $clean = HtmlSanitizer::clean('<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>');

        $this->assertStringNotContainsString('data:text/html', $clean);
    }
}
