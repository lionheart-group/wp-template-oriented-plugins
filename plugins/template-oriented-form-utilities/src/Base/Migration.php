<?php

namespace TofuPlugin\Base;

abstract class Migration
{
    /**
     * Get the SQL statement for the migration
     *
     * @return string
     */
    abstract public function sql(): string;

    /**
     * Whether to execute the migration SQL via $wpdb->query() instead of dbDelta().
     *
     * Override and return true for migrations that use ALTER TABLE or other DDL
     * statements that dbDelta() does not handle reliably.
     * Existing migrations that do not override this method continue to use dbDelta().
     *
     * @return bool
     */
    public function useRawQuery(): bool
    {
        return false;
    }
}
