<?php

namespace Tests\Unit;

use App\Integrations\OnlineNic\Ote\OteValidationReporter;
use PHPUnit\Framework\TestCase;

final class OteValidationReporterTest extends TestCase
{
    public function test_report_has_safe_matrix_and_never_requires_sensitive_values(): void
    {
        $reporter = new OteValidationReporter;
        $reporter->add('GetAuthcode', 'PASS', 1000, 'auth code returned successfully; value omitted');

        $markdown = $reporter->markdown();

        $this->assertStringContainsString('| GetAuthcode | PASS | 1000 |', $markdown);
        $this->assertStringContainsString('value omitted', $markdown);
        $this->assertStringNotContainsString('password', strtolower($markdown));
        $this->assertStringNotContainsString('<response>', $markdown);
    }
}
