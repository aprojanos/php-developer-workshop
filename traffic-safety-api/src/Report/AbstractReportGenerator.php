<?php
namespace App\Report;

/**
 * Template Method base class for report generators.
 * Subclasses must provide items and item formatting.
 */
abstract class AbstractReportGenerator
{
    /**
     * Generate the full report and return it as a string.
     */
    public function generate(): string
    {
        $items = $this->getItems();
        $out = $this->header();
        foreach ($items as $item) {
            $out .= $this->formatItem($item);
        }
        $out .= $this->footer();
        return $out;
    }

    /**
     * Return an array of items to be included in the report.
     * @return array
     */
    abstract protected function getItems(): array;

    /**
     * Optional header for the report.
     */
    protected function header(): string
    {
        return '';
    }

    /**
     * Format a single item. Must return a string (including newline if required).
     * @param mixed $item
     */
    abstract protected function formatItem(mixed $item): string;

    /**
     * Optional footer for the report.
     */
    protected function footer(): string
    {
        return '';
    }
}
