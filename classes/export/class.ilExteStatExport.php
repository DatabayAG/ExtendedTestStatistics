<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use ILIAS\ResourceStorage\Services as ResourceStorage;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\Test\ExportImport\DBRepository as TestExportImportRepository;
use ILIAS\Test\ExportImport\Types as ExportImportTypes;
use ILIAS\Test\TestDIC;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Resource\ResourceType;

/**
 * Extended Test Statistics Export
 *
 * @author Fred Neumann <fred.neumann@ili.fau.de>
 * @author Jesus Copado <jesus.copado@ili.fau.de>
 *
 */
class ilExteStatExport
{
    public const TYPE_EXCEL = 'excel';
    public const TYPE_CSV = 'csv';

    public const LEVEL_TEST = 'test';
    public const LEVEL_QUESTIONS = 'questions';


    private array $headerStyle = [
        'font' => [
            'bold' => true
        ],
        'fill' => [
            'type' => 'solid',
            'color' => ['rgb' => 'DDDDDD'],
        ]
    ];

    private array $rowStyles = [
        0 => [
            'fill' => [
                'type' => 'solid',
                'color' => ['rgb' => 'FFFFFF'],
            ]
        ],
        1 => [
            'fill' => [
                'type' => 'solid',
                'color' => ['rgb' => 'EEEEEE'],
            ]
        ],
    ];


    private ilLanguage $lng;
    private ilExteStatValueExcel $valView;
    private ResourceStorage $resource_storage;
    private ilExteStatExportRepo $export_repo;



    /**
     * Constructor.
     * @param string $level ('test' or 'question')
     */
    public function __construct(
        private ilExtendedTestStatisticsPlugin $plugin,
        private ilExtendedTestStatistics $statObj
    ) {
        global $DIC;

        $this->lng = $DIC->language();
        $this->resource_storage = $DIC->resourceStorage();
        $this->export_repo = new ilExteStatExportRepo($DIC->database());

        $this->statObj = $statObj;
        $this->plugin = $plugin;

        $this->valView = new ilExteStatValueExcel($this->plugin);
    }


    /**
     * Build an Excel Export file
     * @param string	$path	full path of the file to create
     */
    public function buildExportFile(
        string $name,
        string $type = self::TYPE_EXCEL,
        string $level = self::LEVEL_TEST,
        bool $details = false
    ): ResourceIdentification {
        $excelObj = new Spreadsheet();

        if ($type == self::TYPE_CSV) {
            // Create the overview sheet(s)
            switch ($level) {
                case ilExtendedTestStatistics::LEVEL_TEST:
                    $this->fillTestOverview($excelObj->getActiveSheet());
                    break;

                case ilExtendedTestStatistics::LEVEL_QUESTION:
                    $this->fillQuestionsOverview($excelObj->getActiveSheet());
                    break;
            }
        } elseif ($type == self::TYPE_EXCEL) {
            $this->fillLegend($excelObj->getActiveSheet());

            // Create the overview sheet(s)
            if (empty($level) || $level == ilExtendedTestStatistics::LEVEL_TEST) {
                $this->fillTestOverview($excelObj->createSheet());
            }

            if (empty($level) || $level == ilExtendedTestStatistics::LEVEL_QUESTION) {
                $this->fillQuestionsOverview($excelObj->createSheet());
            }

            // Create the details worksheets
            if ($details) {
                if (empty($level) || $level == ilExtendedTestStatistics::LEVEL_TEST) {
                    /** @var  ilExteEvalTest $evaluation */
                    foreach ($this->statObj->getEvaluations(
                        ilExtendedTestStatistics::LEVEL_TEST,
                        ilExtendedTestStatistics::PROVIDES_DETAILS
                    ) as $class => $evaluation) {
                        $this->addTestDetailsSheet($excelObj, $evaluation);
                    }
                }

                if (empty($level) || $level == ilExtendedTestStatistics::LEVEL_QUESTION) {
                    /** @var  ilExteEvalQuestion $evaluation */
                    foreach ($this->statObj->getEvaluations(
                        ilExtendedTestStatistics::LEVEL_QUESTION,
                        ilExtendedTestStatistics::PROVIDES_DETAILS
                    ) as $class => $evaluation) {
                        $this->addQuestionsDetailsSheet($excelObj, $evaluation);
                    }
                }
            }
        }

        $excelObj->setActiveSheetIndex(0);

        // Save the file
        $path = ilFileUtils::ilTempnam();
        ilFileUtils::createDirectory($path);

        $suffix = '';
        switch ($type) {
            case self::TYPE_EXCEL:
                $suffix = '.xlsx';
                $writer = IOFactory::createWriter($excelObj, 'Xlsx');
                $writer->save($path . '/' . $name . $suffix);
                break;

            case self::TYPE_CSV:
                $suffix = '.csv';
                $writer = IOFactory::createWriter($excelObj, 'Csv');
                $writer->setDelimiter(';');
                $writer->setEnclosure('"');
                $writer->save($path . '/' . $name . $suffix);
                break;
        }

        // put in storage and register as export
        $fp = fopen($path . '/' . $name . $suffix, 'r');
        $resource_id = $this->resource_storage->manage()->stream(
            Streams::ofResource($fp),
            new ilExtendedTestStatisticsStakeholder(),
            $name . $suffix
        );
        $this->export_repo->store($this->statObj->getObjId(), $resource_id);

        unlink($path . '/' . $name . $suffix);
        return $resource_id;
    }

    public function deliverExportFile(ResourceIdentification $resource_id): void
    {
        if ($this->export_repo->has($this->statObj->getObjId(), $resource_id)) {
            $this->resource_storage->consume()->download($resource_id)->run();
        }
    }

    /**
     * Fill the legend sheet
     */
    protected function fillLegend(Worksheet $worksheet)
    {
        $comments = array();

        $row = 1;

        // title
        $cell = $worksheet->getCell('A' . $row);
        $cell->setValue($this->lng->txt('title'));
        $cell->getStyle()->applyFromArray($this->headerStyle);
        $cell = $worksheet->getCell('B' . $row);
        $cell->setValue($this->statObj->getSourceData()->getTestTitle());
        $row++;

        // type
        switch ($this->statObj->getSourceData()->getTestType()) {
            case ilExteEvalBase::TEST_TYPE_FIXED:
                $type = $this->lng->txt('tst_question_set_type_fixed');
                $desc = $this->lng->txt('tst_question_set_type_fixed_desc');
                break;
            case ilExteEvalBase::TEST_TYPE_RANDOM:
                $type = $this->lng->txt('tst_question_set_type_random');
                $desc = $this->lng->txt('tst_question_set_type_random_desc');
                break;
            default:
                $type = '';
                $desc = '';
        }
        $cell = $worksheet->getCell('A' . $row);
        $cell->setValue($this->lng->txt('type'));
        $cell->getStyle()->applyFromArray($this->headerStyle);
        $cell = $worksheet->getCell('B' . $row);
        $cell->setValue($type);
        $comments['B' . $row] = ilExteStatValueExcel::_createComment($desc);
        $row++;

        // evaluated pass
        switch ($this->statObj->getSourceData()->getPassSelection()) {
            case ilExteStatSourceData::PASS_SCORED:
                $pass = $this->plugin->txt('pass_scored');
                break;
            case ilExteStatSourceData::PASS_BEST:
                $pass = $this->plugin->txt('pass_best');
                break;
            case ilExteStatSourceData::PASS_FIRST:
                $pass = $this->plugin->txt('pass_first');
                break;
            case ilExteStatSourceData::PASS_LAST:
                $pass = $this->plugin->txt('pass_last');
                break;
            default:
                $pass = '';
        }
        $cell = $worksheet->getCell('A' . $row);
        $cell->setValue($this->plugin->txt('evaluated_pass'));
        $cell->getStyle()->applyFromArray($this->headerStyle);
        $cell = $worksheet->getCell('B' . $row);
        $cell->setValue($pass);
        $row++;

        // export date
        $cell = $worksheet->getCell('A' . $row);
        $cell->setValue($this->lng->txt('export'));
        $cell->getStyle()->applyFromArray($this->headerStyle);
        $cell = $worksheet->getCell('B' . $row);

        ilDatePresentation::setUseRelativeDates(false);
        $cell->setValue(ilDatePresentation::formatDate(new ilDateTime(time(), IL_CAL_UNIX)));
        $row++;



        // legend header
        $row++;
        $cell = $worksheet->getCell('A' . $row);
        $cell->setValueExplicit($this->plugin->txt('legend_symbol_format'), DataType::TYPE_STRING);
        $cell->getStyle()->applyFromArray($this->headerStyle);
        $cell = $worksheet->getCell('B' . $row);
        $cell->setValueExplicit($this->lng->txt('description'), DataType::TYPE_STRING);
        $cell->getStyle()->applyFromArray($this->headerStyle);
        $row++;

        //legend
        foreach ($this->valView->getLegendData() as $data) {
            $value = $data['value'];

            $cell = $worksheet->getCell('A' . $row);
            $this->valView->writeInCell($cell, $value);
            if (!empty($value->comment)) {
                $comments['A' . $row] = $this->valView->getComment($value);
            }

            $cell = $worksheet->getCell('B' . $row);
            $cell->setValueExplicit($data['description'], DataType::TYPE_STRING);
            $row++;
        }

        $worksheet->setTitle($this->lng->txt('legend'));
        $worksheet->setComments($comments);
        $this->adjustSizes($worksheet);
    }

    /**
     * Fill the test overview sheet
     */
    protected function fillTestOverview(Worksheet $worksheet)
    {
        $data = array();
        /** @var ilExteStatValue[]  $values */
        $values = $this->statObj->getSourceData()->getBasicTestValues();
        foreach ($this->statObj->getSourceData()->getBasicTestValuesList() as $def) {
            $data[] = array(
                'title' => $def['title'],
                'description' => $def['description'],
                'value' => $values[$def['id']],
                'details' => null
            );
        }

        /** @var  ilExteEvalTest $evaluation */
        foreach ($this->statObj->getEvaluations(
            ilExtendedTestStatistics::LEVEL_TEST,
            ilExtendedTestStatistics::PROVIDES_VALUE
        ) as $class => $evaluation) {
            $data[] = array(
                'title' => $evaluation->getTitle(),
                'description' => $evaluation->getDescription(),
                'value' => $evaluation->getValue()
            );
        }

        // Debug value formats
        if ($this->plugin->debugFormats()) {
            foreach (ilExteStatValue::_getDemoValues() as $value) {
                $data[] = array(
                    'title' => $value->comment,
                    'description' => '',
                    'value' => $value,
                );
            }
        }

        $rownum = 0;
        $comments = array();
        foreach ($data as $row) {
            $rownum++;

            // title
            $cell = $worksheet->getCell('A' . $rownum);
            $cell->setValueExplicit($row['title'], DataType::TYPE_STRING);
            $cell->getStyle()->applyFromArray($this->headerStyle);
            if (!empty($row['description'])) {
                $comments['A' . $rownum] = ilExteStatValueExcel::_createComment((string) ($row['description'] ?? ''));
            }

            /** @var ilExteStatValue $value */
            $value = $row['value'];
            $cell = $worksheet->getCell('B' . $rownum);
            $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $this->valView->writeInCell($cell, $value);
            if (!empty($value->comment)) {
                $comments['B' . $rownum] = $this->valView->getComment($value);
            }
        }

        $worksheet->setTitle($this->plugin->txt('test_results'));
        $worksheet->setComments($comments);
        $this->adjustSizes($worksheet);
    }

    /**
     * Fill the questions overview sheet
     */
    protected function fillQuestionsOverview(Worksheet $worksheet)
    {
        $header = $this->statObj->getSourceData()->getBasicQuestionValuesList();

        /** @var  ilExteEvalQuestion $evaluation */
        $evaluations = array();
        foreach ($this->statObj->getEvaluations(
            ilExtendedTestStatistics::LEVEL_QUESTION,
            ilExtendedTestStatistics::PROVIDES_VALUE
        ) as $class => $evaluation) {
            $header[$class] = array(
                'title' => $evaluation->getShortTitle(),
                'description' => $evaluation->getDescription(),
            );
            $evaluations[$class] = $evaluation;
        }

        $comments = array();
        $mapping = array();
        $col = 1;
        foreach ($header as $name => $def) {
            if (!empty($def['test_types']) && !in_array($this->statObj->getSourceData()->getTestType(), $def['test_types'])) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($col);
            $mapping[$name] = $letter;
            $coordinate = $letter . '1';
            $cell = $worksheet->getCell($coordinate);
            $cell->setValueExplicit($def['title'], DataType::TYPE_STRING);
            $cell->getStyle()->applyFromArray($this->headerStyle);
            if (!empty($def['description'])) {
                $comments[$coordinate] = ilExteStatValueExcel::_createComment((string) $def['description']);
            }
            $col++;
        }

        $row = 2;
        foreach ($this->statObj->getSourceData()->getBasicQuestionValues() as $question_id => $values) {
            /**@var  ilExteStatValue $value */
            foreach ($values as $id => $value) {
                if (isset($mapping[$id])) {
                    $coordinate = $mapping[$id] . (string) $row;
                    $cell = $worksheet->getCell($coordinate);
                    $this->valView->writeInCell($cell, $value);
                    if (!empty($value->comment)) {
                        $comments[$coordinate] = $this->valView->getComment($value);
                    }
                }
            }

            /** @var  ilExteEvalQuestion $evaluation */
            foreach ($evaluations as $class => $evaluation) {
                if (isset($mapping[$class])) {
                    $coordinate = $mapping[$class] . (string) $row;
                    $cell = $worksheet->getCell($coordinate);
                    $value = $evaluation->getValue($question_id);
                    $this->valView->writeInCell($cell, $value);
                    if (!empty($value->comment)) {
                        $comments[$coordinate] = $this->valView->getComment($value);
                    }
                }
            }

            $row++;
        }

        $worksheet->setTitle($this->plugin->txt('questions_results'));
        $worksheet->setComments($comments);
        $worksheet->freezePane('A2');
        $this->adjustSizes($worksheet, range('A', 'C'));
    }


    /**
     * Add a sheet with details for the test
     */
    protected function addTestDetailsSheet(Spreadsheet $excelObj, ilExteEvalTest $evaluation)
    {
        $worksheet = $excelObj->createSheet();
        $worksheet->setTitle($evaluation->getDetailsTitle());

        $details = $evaluation->getDetails();
        if (empty($details->rows)) {
            $worksheet->setCellValue('A1', $details->getEmptyMessage() ?? $this->lng->txt('no_items'));
            return;
        }

        $col = 1;
        $comments = array();
        $mapping = array();
        foreach ($details->columns as $column) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $mapping[$column->name] = $letter;
            $coordinate = $letter . '1';
            $cell = $worksheet->getCell($coordinate);
            $cell->setValueExplicit($column->title, DataType::TYPE_STRING);
            $cell->getStyle()->applyFromArray($this->headerStyle);
            if (!empty($column->comment)) {
                $comments[$coordinate] = ilExteStatValueExcel::_createComment((string) $column->comment);
            }
            $col++;
        }

        $row = 2;
        foreach ($details->rows as $coldata) {
            /**@var  ilExteStatValue $value */
            foreach ($coldata as $name => $value) {
                if (isset($mapping[$name])) {
                    $coordinate = $mapping[$name] . (string) $row;
                    $cell = $worksheet->getCell($coordinate);
                    $this->valView->writeInCell($cell, $value);
                    if (!empty($value->comment)) {
                        $comments[$coordinate] = $this->valView->getComment($value);
                    }
                }
            }
            $row++;
        }

        $worksheet->setComments($comments);
        $worksheet->freezePane('A2');
        $this->adjustSizes($worksheet);
    }


    /**
     * Add a sheet with details for the test
     */
    protected function addQuestionsDetailsSheet(Spreadsheet $excelObj, ilExteEvalQuestion $evaluation)
    {
        $worksheet = $excelObj->createSheet();
        $worksheet->setTitle($evaluation->getShortTitle());

        if (!$evaluation->isTestTypeAllowed()) {
            $worksheet->setCellValue('A1', $evaluation->getMessageNotAvailableForQuestionType());
            return;
        }

        $columns = array();
        $mapping = array();
        $comments = array();
        $columns['_question_id'] = ilExteStatColumn::_create('_question_id', $this->lng->txt('question_id'));
        $columns['_question_title'] = ilExteStatColumn::_create('_question_title', $this->lng->txt('question_title'));
        $mapping['_question_id'] = 'A';
        $mapping['_question_title'] = 'B';

        $row = 2;
        $col = 2;
        $qst = 0;
        foreach ($this->statObj->getSourceData()->getBasicQuestionValues() as $question_id => $questionValues) {
            $details = $evaluation->getDetails($question_id);
            if (!empty($details->rows)) {
                // add columns that are not yet defined
                foreach ($details->columns as $column) {
                    if (!isset($columns[$column->name])) {
                        $letter = Coordinate::stringFromColumnIndex($col);
                        $columns[$column->name] = $column;
                        $mapping[$column->name] = $letter;
                        $col++;
                    }
                }
                //write lines of the evaluation
                foreach ($details->rows as $rowValues) {
                    // question id
                    $cell = $worksheet->getCell('A' . $row);
                    $cell->getStyle()->applyFromArray($this->rowStyles[$qst % 2]);
                    $this->valView->writeInCell($cell, $questionValues['question_id']);
                    // question title
                    $cell = $worksheet->getCell('B' . $row);
                    $cell->getStyle()->applyFromArray($this->rowStyles[$qst % 2]);
                    $this->valView->writeInCell($cell, $questionValues['question_title']);

                    foreach ($rowValues as $name => $value) {
                        if (isset($mapping[$name])) {
                            $coordinate = $mapping[$name] . $row;
                            $cell = $worksheet->getCell($coordinate);
                            //$cell->getStyle()->applyFromArray($this->rowStyles[$row % 2]);
                            $this->valView->writeInCell($cell, $value);
                            if (!empty($value->comment)) {
                                $comments[$coordinate] = $this->valView->getComment($value);
                            }
                        }
                    }
                    $row++;
                }
            }
            $qst++;
        }

        // write the header row with column titles
        // can be written when all columns are known
        foreach ($columns as $column) {
            $coordinate = $mapping[$column->name] . '1';
            $cell = $worksheet->getCell($coordinate);
            $cell->setValueExplicit($column->title, DataType::TYPE_STRING);
            $cell->getStyle()->applyFromArray($this->headerStyle);
            if (!empty($column->comment)) {
                $comments[$coordinate] = ilExteStatValueExcel::_createComment((string) $column->comment);
            }
        }

        $worksheet->setComments($comments);
        $worksheet->freezePane('A2');
        $this->adjustSizes($worksheet);
    }


    /**
     * Adjust the column sizes
     */
    protected function adjustSizes(worksheet $worksheet, ?array $range = null)
    {
        $range = isset($range) ? $range : range('A', $worksheet->getHighestColumn());
        foreach ($range as $columnID) {
            $worksheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }
}
