<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend as ChartLegend;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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

    protected array $headerStyle = array(
        'font' => array(
            'bold' => true
        ),
        'fill' => array(
            'type' => 'solid',
            'color' => array('rgb' => 'DDDDDD'),
        )
    );

    protected array $rowStyles = array(
        0 => array(
            'fill' => array(
                'type' => 'solid',
                'color' => array('rgb' => 'FFFFFF'),
            )),
        1 => array(
            'fill' => array(
                'type' => 'solid',
                'color' => array('rgb' => 'EEEEEE'),
            )),
    );


    protected ilLanguage $lng;
    protected ilExtendedTestStatisticsPlugin $plugin;
    protected ilExtendedTestStatistics $statObj;
    protected ilExteStatValueExcel $valView;

    /**  Writer Type ('Excel2007' or 'CSV') */
    protected string $type;

    /** Evaluation Level ('test' or 'questions') */
    protected string $level;

    protected bool $details = false;





    /**
     * Constructor.
     */
    public function __construct(
        ilExtendedTestStatisticsPlugin $plugin,
        ilExtendedTestStatistics $statObj,
        string $type = self::TYPE_EXCEL,
        string $level = '',
        bool $details = false
    ) {
        global $DIC;

        $this->lng = $DIC->language();
        $this->statObj = $statObj;
        $this->plugin = $plugin;
        $this->type = $type;
        $this->level = $level;
        $this->details = $details;

        $this->valView = new ilExteStatValueExcel($this->plugin);
    }


    /**
     * Build an Excel Export file
     * @param string	$path	full path of the file to create
     */
    public function buildExportFile(string $path)
    {
        $excelObj = new Spreadsheet();
        //$excelObj->removeSheetByIndex(0);

        if ($this->type == self::TYPE_CSV) {
            // Create the overview sheet(s)
            switch ($this->level) {
                case ilExtendedTestStatistics::LEVEL_TEST:
                    $this->fillTestOverview($excelObj->getActiveSheet());
                    break;

                case ilExtendedTestStatistics::LEVEL_QUESTION:
                    $this->fillQuestionsOverview($excelObj->getActiveSheet());
                    break;
            }
        } elseif ($this->type == self::TYPE_EXCEL) {
            $this->fillLegend($excelObj->getActiveSheet());

            // Create the overview sheet(s)
            if (empty($this->level) || $this->level == ilExtendedTestStatistics::LEVEL_TEST) {
                $this->fillTestOverview($excelObj->createSheet());
            }

            if (empty($this->level) || $this->level == ilExtendedTestStatistics::LEVEL_QUESTION) {
                $this->fillQuestionsOverview($excelObj->createSheet());
            }

            // Create the details worksheets
            if ($this->details) {
                if (empty($this->level) || $this->level == ilExtendedTestStatistics::LEVEL_TEST) {
                    /** @var  ilExteEvalTest $evaluation */
                    foreach ($this->statObj->getEvaluations(
                        ilExtendedTestStatistics::LEVEL_TEST,
                        ilExtendedTestStatistics::PROVIDES_DETAILS
                    ) as $class => $evaluation) {
                        $this->addTestDetailsSheet($excelObj, $evaluation);
                    }
                }

                if (empty($this->level) || $this->level == ilExtendedTestStatistics::LEVEL_QUESTION) {
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

        // $this->addSampleChart($excelObj);
        $excelObj->setActiveSheetIndex(0);

        // Save the file
        ilFileUtils::makeDirParents(dirname($path));
        switch ($this->type) {
            case self::TYPE_EXCEL:

                $writer = IOFactory::createWriter($excelObj, 'Xlsx');
                $writer->save($path, IWriter::SAVE_WITH_CHARTS);
                break;

            case self::TYPE_CSV:
                /** @var Csv $writer */
                $writer = IOFactory::createWriter($excelObj, 'Csv');
                $writer->setDelimiter(';');
                $writer->setEnclosure('"');
                $writer->save($path);
                break;
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
            case ilExteEvalBase::TEST_TYPE_DYNAMIC:
                $type = $this->lng->txt('tst_question_set_type_dynamic');
                $desc = $this->lng->txt('tst_question_set_type_dynamic_desc');
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
        $comments = array();
        $mapping = array();
        /** @var  ilExteEvalQuestion $evaluation */
        $evaluations = array();

        // Basic question columns
        $header = $this->statObj->getSourceData()->getBasicQuestionValuesList();

        // Single evaluation values
        foreach ($this->statObj->getEvaluations(
            ilExtendedTestStatistics::LEVEL_QUESTION,
            ilExtendedTestStatistics::PROVIDES_VALUE
        ) as $class => $evaluation) {
            $header['Single:' . $class] = array(
                'title' => $evaluation->getShortTitle(),
                'description' => $evaluation->getDescription()
            );
            $evaluations['Single:' . $class] = $evaluation;
        }

        // multi evaluation columns
        foreach ($this->statObj->getEvaluations(ilExtendedTestStatistics::LEVEL_QUESTION) as $class => $evaluation) {
            if ($evaluation instanceof ilExteEvalQuestionOverview) {
                $header['Multi:' . $class] = array(
                    'title' => $evaluation->getOverviewSpanningHeader(),
                    'description' => '',
                    'multi' => true
                );
                $evaluations['Multi:' . $class] = $evaluation;
            }
        }

        // Add header cells

        $col = 1;
        foreach ($header as $name => $def) {
            if (!empty($def['test_types']) && !in_array($this->statObj->getSourceData()->getTestType(), $def['test_types'])) {
                continue;
            }
            $letter = Coordinate::stringFromColumnIndex($col);
            $coordinate = $letter . '2';
            $mapping[$name] = $letter;

            if (!($def['multi'] ?? false)) {
                // basic question value or single value from an evaluation
                $cell = $worksheet->getCell($coordinate);
                $cell->setValueExplicit($def['title'], DataType::TYPE_STRING);
                $cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $cell->getStyle()->applyFromArray($this->headerStyle);
                if (!empty($def['description'])) {
                    $comments[$coordinate] = ilExteStatValueExcel::_createComment((string) $def['description']);
                }
                $col++;
            } else {
                // multi columns provided by an evaluation
                /** @var ilExteEvalQuestionOverview $eval */
                $eval = $evaluations[$name];
                $span_first = $col;
                $span_last = $col;
                foreach ($eval->getOverviewColumns() as $column) {
                    $span_last = $col;
                    $cell = $worksheet->getCell($coordinate);
                    $cell->getStyle()->getNumberFormat()->setFormatCode(is_numeric($column->title) ? NumberFormat::FORMAT_NUMBER : NumberFormat::FORMAT_TEXT);
                    $cell->setValueExplicit($column->title, is_numeric($column->title) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    $cell->getStyle()->applyFromArray($this->headerStyle);
                    if (!empty($column->comment)) {
                        $comments[$coordinate] = ilExteStatValueExcel::_createComment($column->comment);
                    }
                    $col++;
                    $letter = Coordinate::stringFromColumnIndex($col);
                    $coordinate = $letter . '2';
                }
                if (!empty($eval->getOverviewSpanningHeader())) {
                    $cell = $worksheet->getCell(Coordinate::stringFromColumnIndex($span_first) . '1');
                    $cell->setValueExplicit($eval->getOverviewSpanningHeader(), DataType::TYPE_STRING);
                    $cell->getStyle()->applyFromArray($this->headerStyle);
                    $cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                    $cell->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $worksheet->mergeCells(Coordinate::stringFromColumnIndex($span_first) . '1'
                        . ':' . Coordinate::stringFromColumnIndex($span_last) . '1');
                }
            }
        }

        // Add value rows

        $row = 3;
        foreach ($this->statObj->getSourceData()->getBasicQuestionValues() as $question_id => $values) {
            // Basic question values
            /**@var  ilExteStatValue $value */
            foreach ($values as $id => $value) {
                if (isset($mapping[$id])) {
                    $coordinate = $mapping[$id] . $row;
                    $cell = $worksheet->getCell($coordinate);
                    $this->valView->writeInCell($cell, $value);
                    if (!empty($value->comment)) {
                        $comments[$coordinate] = $this->valView->getComment($value);
                    }
                }
            }

            /** @var  ilExteEvalQuestion $evaluation */
            foreach ($evaluations as $name => $evaluation) {
                if (!isset($header[$name])) {
                    continue;
                } elseif (!($header[$name]['multi'] ?? false)) {
                    // single evaluation value
                    $coordinate = $mapping[$name] . $row;
                    $cell = $worksheet->getCell($coordinate);
                    $value = $evaluation->getValue($question_id);
                    $this->valView->writeInCell($cell, $value);
                    if (!empty($value->comment)) {
                        $comments[$coordinate] = $this->valView->getComment($value);
                    }
                } else {
                    // multi evaluation columns
                    $col = Coordinate::columnIndexFromString($mapping[$name]);
                    foreach ($evaluation->getOverviewValues($question_id) as $value) {
                        $coordinate = Coordinate::stringFromColumnIndex($col) . $row;
                        $cell = $worksheet->getCell($coordinate);
                        $this->valView->writeInCell($cell, $value);
                        if (!empty($value->comment)) {
                            $comments[$coordinate] = $this->valView->getComment($value);
                        }
                        $col++;
                    }
                }
            }

            $row++;
        }

        $worksheet->setTitle($this->plugin->txt('questions_results'));
        $worksheet->setComments($comments);
        $worksheet->freezePane('A3');
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
                $comments[$coordinate] = ilExteStatValueExcel::_createComment($column->comment);
            }
            $col++;
        }

        $row = 2;
        foreach ($details->rows as $coldata) {
            /**@var  ilExteStatValue $value */
            foreach ($coldata as $name => $value) {
                if (isset($mapping[$name])) {
                    $coordinate = $mapping[$name] . $row;
                    $cell = $worksheet->getCell($coordinate);
                    $this->valView->writeInCell($cell, $value);
                    if (!empty($value->comment)) {
                        $comments[$coordinate] = $this->valView->getComment($value);
                    }
                }
            }
            $row++;
        }

        if ($chart = $evaluation->getExcelChartDefinition()) {
            $this->addChart(
                $worksheet,
                $chart->getTitle(),
                $mapping[$chart->getXColumn()] ?? 'A',
                $mapping[$chart->getYColumn()] ?? 'B',
                2,
                count($details->rows),
            );
        }

        $worksheet->setComments($comments);
        $worksheet->freezePane('A2');
        $this->adjustSizes($worksheet);
    }

    protected function addChart(Worksheet $sheet, string $title, string $col_x, string $col_y, int $start_row, int $rows)
    {
        $x_range = $sheet->getTitle() . '!' . $col_x . $start_row . ':' . $col_x . ($start_row + $rows - 1);
        $y_range = $sheet->getTitle() . '!' . $col_y . $start_row . ':' . $col_y . ($start_row + $rows - 1);

        $x_values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $x_range, null, $rows)
        ];

        $y_values = [
             new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $y_range, null, $rows),
         ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            null,
            range(0, count($y_values) - 1),
            [],
            $x_values,
            $y_values
        );

        $layout = new Layout();
        $layout->setShowVal(true);

        $plotArea = new PlotArea($layout, [$series]);

        // Create the chart
        $chart = new Chart(
            'Chart ' . $sheet->getTitle(),
            new Title($title),
            null,
            new PlotArea($layout, [$series]),
            true,
            DataSeries::EMPTY_AS_GAP,
        );

        // Set the position where the chart should appear in the worksheet
        $chart->setTopLeftPosition('A' . ($rows + 5));
        $chart->setBottomRightPosition('H' . ($rows + 35));

        // Add the chart to the worksheet
        $sheet->addChart($chart);
    }

    protected function addSampleChart(Spreadsheet $excelObj)
    {
        $worksheet = $excelObj->createSheet();
        $worksheet->setTitle('Sample');

        $worksheet->fromArray(
            [
                ['', 2010, 2011, 2012],
                ['Q1', 12, 15, 21],
                ['Q2', 56, 73, 86],
                ['Q3', 52, 61, 69],
                ['Q4', 30, 32, 0],
            ]
        );

        // Custom colors for dataSeries (gray, blue, red, orange)
        $colors = [
            'cccccc', '00abb8', 'b8292f', 'eb8500',
        ];

        // Set the Labels for each data series we want to plot
        //     Datatype
        //     Cell reference for data
        //     Format Code
        //     Number of datapoints in series
        //     Data values
        //     Data Marker
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Sample!$B$1', null, 1), // 2010
        ];
        // Set the X-Axis Labels
        //     Datatype
        //     Cell reference for data
        //     Format Code
        //     Number of datapoints in series
        //     Data values
        //     Data Marker
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Sample!$A$2:$A$5', null, 4), // Q1 to Q4
        ];

        // Set the Data values for each data series we want to plot
        //     Datatype
        //     Cell reference for data
        //     Format Code
        //     Number of datapoints in series
        //     Data values
        //     Data Marker
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Sample!$B$2:$B$5', null, 4, [], null, $colors),
        ];

        // Build the dataseries
        $series = new DataSeries(
            DataSeries::TYPE_BARCHART, // plotType
            null, // plotGrouping
            range(0, count($dataSeriesValues) - 1), // plotOrder
            $dataSeriesLabels, // plotLabel
            $xAxisTickValues, // plotCategory
            $dataSeriesValues        // plotValues
        );

        // Set up a layout object for the Pie chart
        $layout = new Layout();
        $layout->setShowVal(true);
        $layout->setShowPercent(true);

        // Set the series in the plot area
        $plotArea = new PlotArea($layout, [$series]);
        // Set the chart legend
        $legend = new ChartLegend(ChartLegend::POSITION_RIGHT, null, false);

        $title = new Title('Notenspiegel');
        $yAxisLabel = new Title('Value');

        // Create the chart
        $chart = new Chart(
            'chart1', // name
            $title, // title
            $legend, //$legend, // legend
            $plotArea, // plotArea
            true, // plotVisibleOnly
            DataSeries::EMPTY_AS_GAP, // displayBlanksAs
            null, // xAxisLabel
            null, // $yAxisLabel  // yAxisLabel
        );

        // Set the position where the chart should appear in the worksheet
        $chart->setTopLeftPosition('A10');
        $chart->setBottomRightPosition('H30');

        // Add the chart to the worksheet
        $worksheet->addChart($chart);
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
                $comments[$coordinate] = ilExteStatValueExcel::_createComment($column->comment);
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
        $range = $range ?? range('A', $worksheet->getHighestColumn());
        foreach ($range as $columnID) {
            $worksheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }
}
