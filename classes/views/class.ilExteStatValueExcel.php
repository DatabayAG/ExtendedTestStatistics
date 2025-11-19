<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE


use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Comment;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\RichText\TextElement;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Excel representation of a statistical values
 */
class ilExteStatValueExcel
{
    public const COLOR_GOOD = 'A0FFA0';
    public const COLOR_MEDIUM = 'FFFFA0';
    public const COLOR_BAD = 'FFA0A0';
    public const COLOR_UNKNOWN = 'CCCCCC';
    public const COLOR_NONE = 'FFFFFF';

    protected ilLanguage $lng;
    protected ilExtendedTestStatisticsPlugin $plugin;

    /** comments should be shown as tooltip */
    protected bool $show_comment = true;

    /**
     * Constructor.
     */
    public function __construct(ilExtendedTestStatisticsPlugin $a_plugin)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->plugin = $a_plugin;
    }

    /**
     * Fill a cell with the value
     */
    public function writeInCell(Cell $cell, ilExteStatValue $value)
    {
        $numberFormat = $cell->getStyle()->getNumberFormat();
        $alignment = $cell->getStyle()->getAlignment();

        // value
        if (isset($value->value)) {
            switch ($value->type) {
                case ilExteStatValue::TYPE_ALERT:
                case ilExteStatValue::TYPE_TEXT:
                    $cell->setValueExplicit(ilUtil::secureString($value->value), DataType::TYPE_STRING);
                    $numberFormat->setFormatCode(NumberFormat::FORMAT_TEXT);
                    break;

                case ilExteStatValue::TYPE_NUMBER:
                    $cell->setValueExplicit($value->value, DataType::TYPE_NUMERIC);
                    $numberFormat->setFormatCode($value->precision == 0 ?
                        NumberFormat::FORMAT_NUMBER : NumberFormat::FORMAT_NUMBER_00);
                    $alignment->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    break;

                case ilExteStatValue::TYPE_DURATION:
                    $cell->setValueExplicit($value->value / 86400, DataType::TYPE_NUMERIC);
                    $numberFormat->setFormatCode(NumberFormat::FORMAT_DATE_TIME4);
                    $alignment->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    break;

                case ilExteStatValue::TYPE_DATETIME:
                    if ($value->value instanceof ilDateTime) {
                        $cell->setValue(Date::PHPToExcel($value->value->getUnixTime()));
                        $numberFormat->setFormatCode(NumberFormat::FORMAT_DATE_DATETIME);
                        $alignment->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        break;
                    }
                    break;
                case ilExteStatValue::TYPE_PERCENTAGE:
                    $cell->setValueExplicit($value->value / 100, DataType::TYPE_NUMERIC);
                    $numberFormat->setFormatCode($value->precision == 0 ?
                        NumberFormat::FORMAT_PERCENTAGE : NumberFormat::FORMAT_PERCENTAGE_00);
                    $alignment->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    break;

                case ilExteStatValue::TYPE_BOOLEAN:
                    $cell->setValueExplicit((bool) $value->value, DataType::TYPE_BOOL);
                    $numberFormat->setFormatCode(NumberFormat::FORMAT_NUMBER);
                    $alignment->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    break;
            }
        }

        // alert color
        if ($value->alert != ilExteStatValue::ALERT_NONE) {
            switch ($value->alert) {
                case ilExteStatValue::ALERT_GOOD:
                    $color = self::COLOR_GOOD;
                    break;
                case ilExteStatValue::ALERT_MEDIUM:
                    $color = self::COLOR_MEDIUM;
                    break;
                case ilExteStatValue::ALERT_BAD:
                    $color = self::COLOR_BAD;
                    break;
                case ilExteStatValue::ALERT_UNKNOWN:
                    $color = self::COLOR_UNKNOWN;
                    break;
                default:
                    $color = self::COLOR_NONE;
            }

            $cell->getStyle()->getFill()->applyFromArray(array(
                    'fillType' => Fill::FILL_SOLID,
                    'color' => array('rgb' => $color),
            ));
        }

        if ($value->uncertain) {
            $cell->getStyle()->applyFromArray(array(
                'font' => array(
                    'italic' => true
                )
            ));
        }

        if ($value->weight === ilExteStatValue::WEIGHT_STRONG) {
            $cell->getStyle()->applyFromArray(array(
                'font' => array(
                    'bold' => true
                )
            ));
        }

        if ($value->color !== ilExteStatValue::COLOR_BLACK) {
            $cell->getStyle()->applyFromArray(array(
                'font' => array(
                    'color' => array('rgb' => $value->color),
                )
            ));
        }
    }

    /**
     * Get the Excel comment for the value
     */
    public function getComment(ilExteStatValue $value): Comment
    {
        return self::_createComment((string) $value->comment);
    }

    /**
     * Create an excel comment from a text
     */
    public static function _createComment(string $text): Comment
    {
        $comment = new Comment();
        $richText = new RichText();
        $textElement = new TextElement(ilUtil::secureString($text));
        $richText->addText($textElement);
        $comment->setText($richText);
        $comment->setHeight('150pt');
        $comment->setWidth('200pt');
        return $comment;
    }


    /**
     * Get legend data
     * @return array	[ ['value' => ilExteStatValue, 'description' => string], ...]
     */
    public function getLegendData(): array
    {
        $data = array(
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_ALERT, 0, '', ilExteStatValue::ALERT_GOOD),
                'description' => $this->plugin->txt('legend_alert_good')
            ),
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_ALERT, 0, '', ilExteStatValue::ALERT_MEDIUM),
                'description' => $this->plugin->txt('legend_alert_medium')
            ),
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_ALERT, 0, '', ilExteStatValue::ALERT_BAD),
                'description' => $this->plugin->txt('legend_alert_bad')
            ),
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_ALERT, 0, '', ilExteStatValue::ALERT_UNKNOWN),
                'description' => $this->plugin->txt('legend_alert_unknown')
            ),
            array(
                'value' => ilExteStatValue::_create($this->lng->txt('value'), ilExteStatValue::TYPE_TEXT, 0, '', ilExteStatValue::ALERT_NONE, true),
                'description' => $this->plugin->txt('legend_uncertain')
            ),
            array(
                'value' => ilExteStatValue::_create($this->lng->txt('value'), ilExteStatValue::TYPE_TEXT, 0, $this->lng->txt('comment')),
                'description' => $this->plugin->txt('legend_comment')
            )
        );

        return $data;
    }
}
