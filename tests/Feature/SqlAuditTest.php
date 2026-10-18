<?php

namespace Tests\Feature;

use App\Models\Buku;
use App\Models\Kategori;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SqlAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_index_uses_fixed_library_queries_for_ten_books(): void
    {
        $this->seed();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            if (preg_match('/\bfrom\s+"(?:bukus|kategoris)"/i', $query->sql)) {
                $queries[] = $query->sql;
            }
        });

        $this->get(route('buku.index'))->assertOk()
            ->assertViewHas('bukus', fn ($bukus) => $bukus->count() === 10 && $bukus->total() === 20);
        $this->assertCount(6, $queries);
        $this->assertStringContainsString('count(*)', $queries[0]);
        $this->assertStringContainsString('limit 10', $queries[1]);
        $this->assertStringContainsString('"kategoris"."id" in (', $queries[2]);
    }

    public function test_enabled_audit_writes_queries_to_log(): void
    {
        config(['sql_audit.enabled' => true]);
        Log::spy();
        (new AppServiceProvider($this->app))->boot();

        Kategori::count();

        Log::shouldHaveReceived('debug')->once()->withArgs(fn ($message, $context) => $message === 'SQL audit'
            && str_contains($context['sql'], 'kategoris')
            && isset($context['time_ms'], $context['connection']));
    }

    public function test_disabled_audit_does_not_write_queries_to_log(): void
    {
        config(['sql_audit.enabled' => false]);
        Log::spy();
        (new AppServiceProvider($this->app))->boot();

        Buku::count();

        Log::shouldNotHaveReceived('debug');
    }
}
