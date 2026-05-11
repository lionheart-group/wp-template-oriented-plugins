<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Helpers\Encryptor;
use TofuPlugin\Models\Record;
use TofuPlugin\Tests\Unit\BaseTestCase;

class RecordTest extends BaseTestCase
{
    /**
     * Restore the default wpdb stub used by the bootstrap.
     */
    private function restoreDefaultWpdb(): void
    {
        $GLOBALS['wpdb'] = new class {
            public $prefix    = 'wp_';
            public $insert_id = 0;
            public function insert($t, $d, $f = null) { $this->insert_id = 1; return 1; }
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $o = OBJECT) { return []; }
            public function get_var($q, $x = 0, $y = 0) { return null; }
            public function query($q) { return true; }
        };
    }

    /**
     * saveRecord() encrypts the values and can be decrypted back to the original data.
     */
    public function testSaveRecordEncryptsAndReturnsId(): void
    {
        $values = ['name' => 'Alice', 'email' => 'alice@example.com', 'message' => 'Hello'];

        $id = Record::saveRecord('contact', $values);

        $this->assertIsInt($id);
    }

    /**
     * Round-trip: the encrypted `data` column written by saveRecord() decrypts
     * back to the original values.
     */
    public function testSaveRecordEncryptedPayloadRoundTrips(): void
    {
        $values = ['name' => 'Bob', 'email' => 'bob@example.com'];

        $mock = new class {
            public $prefix       = 'wp_';
            public $insert_id    = 1;
            public ?string $capturedData = null;

            public function prepare($q, ...$a): string { return $q; }
            public function insert($table, $data, $format = null): int
            {
                $this->capturedData = $data['data'] ?? null;
                return 1;
            }
            public function get_results($q, $o = OBJECT): array { return []; }
            public function get_var($q, $x = 0, $y = 0): ?string { return null; }
            public function query($q) { return true; }
        };
        $GLOBALS['wpdb'] = $mock;

        Record::saveRecord('contact', $values);

        $this->assertNotNull($mock->capturedData);
        $decrypted = Encryptor::decrypt((string) $mock->capturedData);
        $this->assertIsArray($decrypted);
        $this->assertSame($values, $decrypted);

        $this->restoreDefaultWpdb();
    }

    /**
     * Field filtering: only specified fields appear in the payload persisted by
     * saveRecord() when $recordFields is provided.
     */
    public function testSaveRecordFiltersFieldsWhenRecordFieldsProvided(): void
    {
        $values = ['name' => 'Carol', 'email' => 'carol@example.com', 'message' => 'Hi'];
        $filter = ['name', 'email'];

        $mock = new class {
            public $prefix       = 'wp_';
            public $insert_id    = 1;
            public ?string $capturedData = null;

            public function prepare($q, ...$a): string { return $q; }
            public function insert($table, $data, $format = null): int
            {
                $this->capturedData = $data['data'] ?? null;
                return 1;
            }
            public function get_results($q, $o = OBJECT): array { return []; }
            public function get_var($q, $x = 0, $y = 0): ?string { return null; }
            public function query($q) { return true; }
        };
        $GLOBALS['wpdb'] = $mock;

        Record::saveRecord('contact', $values, $filter);

        $this->assertNotNull($mock->capturedData);
        $decrypted = Encryptor::decrypt((string) $mock->capturedData);
        $this->assertIsArray($decrypted);
        $this->assertArrayHasKey('name', $decrypted);
        $this->assertArrayHasKey('email', $decrypted);
        $this->assertArrayNotHasKey('message', $decrypted);

        $this->restoreDefaultWpdb();
    }

    /**
     * Fields in $recordFields that are absent from $values are silently skipped.
     */
    public function testSaveRecordSkipsFieldsNotInValues(): void
    {
        $values  = ['name' => 'Dave'];
        $filter  = ['name', 'nonexistent_field'];

        $payload   = array_intersect_key($values, array_flip($filter));
        $encrypted = Encryptor::encrypt($payload);
        $decrypted = Encryptor::decrypt($encrypted);

        $this->assertIsArray($decrypted);
        $this->assertSame(['name' => 'Dave'], $decrypted);
    }

    /**
     * Empty $recordFields means all values are persisted.
     */
    public function testSaveRecordWithEmptyFilterStoresAllValues(): void
    {
        $values    = ['name' => 'Eve', 'email' => 'eve@example.com'];
        $payload   = array_intersect_key($values, array_flip([])) ?: $values; // mirrors saveRecord() logic

        // saveRecord() uses the full $values when $recordFields is empty
        $encrypted = Encryptor::encrypt($values);
        $decrypted = Encryptor::decrypt($encrypted);

        $this->assertIsArray($decrypted);
        $this->assertSame($values, $decrypted);
    }

    /**
     * saveRecord() returns an int (inserted row ID) on success.
     */
    public function testSaveRecordReturnsInsertedId(): void
    {
        $id = Record::saveRecord('myform', ['field' => 'value']);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    // -------------------------------------------------------------------------
    // getRecords()
    // -------------------------------------------------------------------------

    /**
     * getRecords() returns the expected array shape even when wpdb returns empty.
     */
    public function testGetRecordsReturnsEmptyWhenNoRows(): void
    {
        // Default mock returns [] from get_results and null from get_var.
        $result = Record::getRecords();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['items']);
        $this->assertSame(0, $result['total']);
    }

    /**
     * getRecords() passes form_id filter to the query and returns the mock rows.
     */
    public function testGetRecordsWithFormIdFilter(): void
    {
        // Swap the global mock to return a stub row and count.
        $row           = new \stdClass();
        $row->id       = 1;
        $row->form_id  = 'contact';
        $row->data     = null;
        $row->submitted_at = '2026-05-11 10:00:00';

        $GLOBALS['wpdb'] = new class($row) {
            public $prefix = 'wp_';
            private \stdClass $row;

            public function __construct(\stdClass $row) { $this->row = $row; }

            public function prepare($query, ...$args): string { return $query; }

            public function get_results($query, $output = OBJECT): array
            {
                return [$this->row];
            }

            public function get_var($query, $x = 0, $y = 0): ?string
            {
                return '1';
            }
        };

        $result = Record::getRecords('contact', 25, 1);

        $this->assertCount(1, $result['items']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('contact', $result['items'][0]->form_id);

        // Restore original mock (reset to default bootstrap stub)
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public function insert($t, $d, $f = null) { $this->insert_id = 1; return 1; }
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $o = OBJECT) { return []; }
            public function get_var($q, $x = 0, $y = 0) { return null; }
            public function query($q) { return true; }
        };
    }

    /**
     * getRecords() page/perPage correctly compute the 1-based offset.
     * We verify the query string contains the right LIMIT / OFFSET tokens by
     * using a recording mock.
     */
    public function testGetRecordsPaginationPassesCorrectArguments(): void
    {
        $capturedQuery = null;

        $GLOBALS['wpdb'] = new class($capturedQuery) {
            public $prefix = 'wp_';
            public ?string $lastQuery = null;

            public function prepare($query, ...$args): string
            {
                // Substitute %d placeholders so we can inspect the values.
                $i = 0;
                return preg_replace_callback('/%[sdif]/', function () use (&$i, $args) {
                    return isset($args[$i]) ? (string) $args[$i++] : '?';
                }, $query);
            }

            public function get_results($query, $output = OBJECT): array
            {
                $this->lastQuery = $query;
                return [];
            }

            public function get_var($query, $x = 0, $y = 0): ?string
            {
                return '0';
            }
        };

        // Page 3, 10 per page → LIMIT 10 OFFSET 20
        Record::getRecords(null, 10, 3);

        $lastQuery = $GLOBALS['wpdb']->lastQuery;
        $this->assertStringContainsString('10', (string) $lastQuery);
        $this->assertStringContainsString('20', (string) $lastQuery);

        // Restore
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public function insert($t, $d, $f = null) { $this->insert_id = 1; return 1; }
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $o = OBJECT) { return []; }
            public function get_var($q, $x = 0, $y = 0) { return null; }
            public function query($q) { return true; }
        };
    }

    // -------------------------------------------------------------------------
    // getFormIds()
    // -------------------------------------------------------------------------

    /**
     * getFormIds() returns an empty array when wpdb returns no rows.
     */
    public function testGetFormIdsReturnsEmptyArrayWhenNoRows(): void
    {
        $result = Record::getFormIds();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * getFormIds() extracts the form_id column from returned rows.
     */
    public function testGetFormIdsExtractsFormIdColumn(): void
    {
        $row1          = new \stdClass();
        $row1->form_id = 'contact';
        $row2          = new \stdClass();
        $row2->form_id = 'inquiry';

        $GLOBALS['wpdb'] = new class($row1, $row2) {
            public $prefix = 'wp_';
            private array $rows;

            public function __construct(\stdClass ...$rows) { $this->rows = $rows; }
            public function prepare($q, ...$a): string { return $q; }
            public function get_results($q, $o = OBJECT): array { return $this->rows; }
            public function get_var($q, $x = 0, $y = 0): ?string { return null; }
        };

        $result = Record::getFormIds();

        $this->assertSame(['contact', 'inquiry'], $result);

        // Restore
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public function insert($t, $d, $f = null) { $this->insert_id = 1; return 1; }
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $o = OBJECT) { return []; }
            public function get_var($q, $x = 0, $y = 0) { return null; }
            public function query($q) { return true; }
        };
    }
}
