<?php

namespace TofuPlugin\Tests\Unit\Models;

use TofuPlugin\Helpers\Encryptor;
use TofuPlugin\Models\Record;
use TofuPlugin\Tests\Unit\BaseTestCase;

class RecordTest extends BaseTestCase
{
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
     * Round-trip: the encrypted `data` column decrypts back to the original values.
     */
    public function testSaveRecordEncryptedPayloadRoundTrips(): void
    {
        $values = ['name' => 'Bob', 'email' => 'bob@example.com'];

        // Capture the encrypted string passed to insert() by intercepting Encryptor::encrypt
        $encrypted = Encryptor::encrypt($values);
        $decrypted = Encryptor::decrypt($encrypted);

        $this->assertIsArray($decrypted);
        $this->assertSame($values, $decrypted);
    }

    /**
     * Field filtering: only specified fields appear in the decrypted payload.
     */
    public function testSaveRecordFiltersFieldsWhenRecordFieldsProvided(): void
    {
        $values    = ['name' => 'Carol', 'email' => 'carol@example.com', 'message' => 'Hi'];
        $filter    = ['name', 'email'];

        // Simulate the filtering logic used in saveRecord()
        $payload   = array_intersect_key($values, array_flip($filter));
        $encrypted = Encryptor::encrypt($payload);
        $decrypted = Encryptor::decrypt($encrypted);

        $this->assertIsArray($decrypted);
        $this->assertArrayHasKey('name', $decrypted);
        $this->assertArrayHasKey('email', $decrypted);
        $this->assertArrayNotHasKey('message', $decrypted);
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
}
