<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class generates formatted tables for CLI output.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Cli\Helpers;

use Exception;

class Table
{
    private array $header = [];

    private array $data = [];

    private array $max_length = [];

    private int $columns;

    private string $line;

    private string $table;

    public function header(?array $header = null): self
    {
        $this->header = $header;
        $this->columns = empty($header) ? null : count($header);

        return $this;
    }

    public function data(?array $data = null): self
    {
        $this->data = $data;

        return $this;
    }

    private function _process_data(): void
    {
        $this->prepareData();
        $this->verifyHeader();
        $this->verifyData();
        $this->getLengths();
        $this->generateHeader();
        $this->generateBody();
    }

    public function getTable(): string
    {
        $this->_process_data();

        return $this->table;
    }

    private function prepareData(): void
    {
        if (! is_array($this->data)) {
            throw new Exception('Data passed must be an array');
        }

        if (is_object($this->data[0])) {
            $this->generateHeaderFromData();
            $this->convertObjectToArray();
        } elseif (is_array($this->data[0])) {
            $this->generateHeaderFromData();
        } else {
            throw new Exception('Passed data must be array of objects or arrays');
        }
    }

    private function convertObjectToArray(): void
    {
        $temp = [];
        foreach ($this->data as $obj) {
            $arr = [];
            foreach ($obj as $item) {
                $arr[] = $item;
            }

            $temp[] = $arr;
        }

        $this->data = $temp;
    }

    private function generateHeaderFromData(): void
    {
        if (! $this->header) {
            $temp = [];
            foreach ($this->data[0] as $key => $item) {
                $temp[] = $key;
            }

            $this->header = $temp;
            $this->columns = count($temp);
        }
    }

    private function generateHeader(): void
    {
        $table = '';
        for ($i = 0; $i < $this->columns; $i++) {
            $table .= '+';
            $len = $this->max_length[$i] + 2;
            $table .= sprintf("%'-{$len}s", '');
        }

        $table .= '+' . PHP_EOL;
        $this->line = $table;

        for ($i = 0; $i < $this->columns; $i++) {
            $len = $this->max_length[$i] + 1;
            $table .= '| ';
            $table .= sprintf("%' -{$len}s", $this->header[$i]);
        }

        $table .= '|' . PHP_EOL;
        $table .= $this->line;
        $this->table = $table;
    }

    private function generateBody(): void
    {
        $table = '';

        foreach ($this->data as $row) {
            $i = 0;

            foreach ($row as $field) {
                $len = $this->max_length[$i] + 1;
                $table .= '| ' . sprintf("%' -{$len}s", $field);
                $i++;
            }

            $table .= '|' . PHP_EOL;
        }

        $this->table .= $table;
        $this->table .= $this->line;
    }

    private function getLengths()
    {
        for ($i = 0; $i < $this->columns; $i++) {
            $this->max_length[$i] = 0;
            foreach ($this->header as $field) {
                if (strlen($field) > $this->max_length[$i]) {
                    $this->max_length[$i] = strlen($field);
                }
            }
        }

        foreach ($this->data as $row) {
            $i = 0;
            foreach ($row as $field) {
                if (strlen($field) > $this->max_length[$i]) {
                    $this->max_length[$i] = strlen($field);
                }

                $i++;
            }
        }
    }

    private function verifyHeader()
    {
        if (! is_array($this->header)) {
            throw new Exception('Table header must be an array');
        }
    }

    private function verifyData(): void
    {
        if (! is_array($this->data)) {
            throw new Exception('Data passed must be an array');
        }

        if (! is_array($this->data[0])) {
            throw new Exception('Data must be an array of arrays');
        }

        if (count($this->data[0]) != $this->columns) {
            throw new Exception('Array length mismatch between table header and the data');
        }
    }
}
