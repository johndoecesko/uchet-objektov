<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Простая книга XLSX (openspout): листы с шапкой, числа — числами, даты — датами.
 * Денежный формат «# ##0,00» (Excel показывает разделители по локали пользователя).
 */
class Xlsx
{
    public const MONEY = '#,##0.00';

    public const NUMBER = '#,##0.0#';

    public const PERCENT = '0%';

    public const DATE = 'dd.mm.yyyy';

    private Writer $writer;

    private string $path;

    private bool $first = true;

    private Style $header;

    private Style $bold;

    /** @var array<string, Style> */
    private array $styles = [];

    public function __construct()
    {
        $this->path = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->writer = new Writer;
        $this->writer->openToFile($this->path);
        $this->header = (new Style)->setFontBold()->setBackgroundColor('E8EEF7')->setShouldWrapText();
        $this->bold = (new Style)->setFontBold();
    }

    /**
     * Новый лист. $columns: [заголовок => [ширина, формат|null]]
     */
    public function sheet(string $name, array $columns = []): static
    {
        $sheet = $this->first ? $this->writer->getCurrentSheet() : $this->writer->addNewSheetAndMakeItCurrent();
        $this->first = false;
        $sheet->setName(mb_substr($name, 0, 31));

        $i = 1;
        foreach ($columns as [$width]) {
            $sheet->setColumnWidth($width, $i++);
        }
        if ($columns) {
            $sheet->setSheetView((new SheetView)->setFreezeRow(2));
            $this->writer->addRow(Row::fromValues(array_keys($columns), $this->header));
        }

        return $this;
    }

    /**
     * Строка значений. $formats — формат для каждой колонки (MONEY, DATE…); $bold — итоговая строка.
     */
    public function row(array $values, array $formats = [], bool $bold = false): static
    {
        $cells = [];
        foreach (array_values($values) as $i => $value) {
            if ($value instanceof DateTimeInterface) {
                $value = Carbon::instance($value)->startOfDay()->toDateTimeImmutable();
            } elseif (is_string($value) && is_numeric($value) && in_array($formats[$i] ?? null, [self::MONEY, self::NUMBER, self::PERCENT], true)) {
                $value = (float) $value;
            }
            $format = $formats[$i] ?? ($value instanceof DateTimeInterface ? self::DATE : null);
            $cells[] = Cell::fromValue($value, $this->style($format, $bold));
        }
        $this->writer->addRow(new Row($cells));

        return $this;
    }

    public function blank(): static
    {
        $this->writer->addRow(Row::fromValues([]));

        return $this;
    }

    /** Заголовок раздела внутри листа */
    public function title(string $text): static
    {
        $this->writer->addRow(Row::fromValues([$text], $this->bold));

        return $this;
    }

    public function download(string $filename): BinaryFileResponse
    {
        $this->writer->close();

        return response()->download($this->path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Для тестов: закрыть и вернуть путь к файлу */
    public function save(): string
    {
        $this->writer->close();

        return $this->path;
    }

    private function style(?string $format, bool $bold): ?Style
    {
        if (! $format && ! $bold) {
            return null;
        }
        $key = $format.'|'.(int) $bold;
        if (! isset($this->styles[$key])) {
            $style = new Style;
            if ($format) {
                $style->setFormat($format);
            }
            if ($bold) {
                $style->setFontBold()->setBackgroundColor(Color::rgb(242, 242, 242));
            }
            $this->styles[$key] = $style;
        }

        return $this->styles[$key];
    }
}
