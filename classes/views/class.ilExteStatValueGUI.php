<?php

// Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ILIAS\UI\Help\Topic as HelpTopic;
use ILIAS\UI\Help\Purpose as HelpPurpose;
use ILIAS\UI\HelpTextRetriever;
use ILIAS\UI\Implementation\Render\TooltipRenderer;
use ILIAS\UI\Implementation\Render\ilJavaScriptBinding;

/**
 * GUI for showing statistical values
 */
class ilExteStatValueGUI implements HelpTextRetriever
{
    private Factory $factory;
    private Renderer $renderer;
    private ilJavaScriptBinding $js_binding;
    private TooltipRenderer $tooltip_renderer;
    protected ilLanguage $lng;
    protected ilExtendedTestStatisticsPlugin $plugin;
    protected ilGlobalTemplateInterface $tpl;

    /** @var bool	comments should be shown as tooltip  */
    protected bool $show_comment = true;

    /** @var string[] help texts */
    protected $help_texts = [];

    /**
     * Constructor.
     */
    public function __construct(ilExtendedTestStatisticsPlugin $a_plugin)
    {
        global $DIC;

        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->factory = $DIC->ui()->factory();
        $this->renderer = $DIC->ui()->renderer();
        $this->plugin = $a_plugin;
        $this->js_binding = new ilJavaScriptBinding($this->tpl);
        $this->tooltip_renderer = new TooltipRenderer($this, fn($path, $f1, $f2) => new ilTemplate($path, $f1, $f2));
    }

    /**
     * Set whether comments should be shown
     */
    public function setShowComment(bool $a_show_comment)
    {
        $this->show_comment = $a_show_comment;
    }

    /**
     * Get the rendered HTML for a value
     */
    public function getHTML(ilExteStatValue $value): string
    {
        $template = $this->plugin->getTemplate('tpl.il_exte_stat_value.html');

        // value
        $content = null;
        $comment = null;
        $sign = null;
        $align = $value->align;

        // prevent casting null to 0 etc.
        if ($value->value === null) {
            $content = '';
        } else {
            switch ($value->type) {
                case ilExteStatValue::TYPE_ALERT:
                    // alert is separately set
                    $content = '';
                    break;

                case ilExteStatValue::TYPE_TEXT:
                    $content = $this->textDisplay((string) $value->value);
                    break;

                case ilExteStatValue::TYPE_NUMBER:
                    $content = sprintf('%01.' . $value->precision . 'f', round($value->value, $value->precision));
                    break;

                case ilExteStatValue::TYPE_DURATION:
                    $diff_seconds = $value->value;
                    $diff_hours = floor($diff_seconds / 3600);
                    $diff_seconds -= $diff_hours * 3600;
                    $diff_minutes = floor($diff_seconds / 60);
                    $diff_seconds -= $diff_minutes * 60;

                    $content = sprintf("%02d:%02d:%02d", $diff_hours, $diff_minutes, $diff_seconds);
                    break;

                case ilExteStatValue::TYPE_DATETIME:
                    if ($value->value instanceof ilDateTime) {
                        $content = ilDatePresentation::formatDate($value->value);
                    }
                    break;

                case ilExteStatValue::TYPE_PERCENTAGE:
                    $content = sprintf('%01.' . $value->precision . 'f', round($value->value, $value->precision)) . '%';
                    break;

                case ilExteStatValue::TYPE_BOOLEAN:
                    $content = ($value->value ? $this->lng->txt('yes') : $this->lng->txt('no'));
                    break;

                default:
                    $content = '';
            }
        }

        if ($align === null) {
            switch ($value->type) {
                case ilExteStatValue::TYPE_ALERT:
                case ilExteStatValue::TYPE_NUMBER:
                case ilExteStatValue::TYPE_DURATION:
                case ilExteStatValue::TYPE_PERCENTAGE:
                case ilExteStatValue::TYPE_BOOLEAN:
                    $align = ilExteStatValue::ALIGN_RIGHT;
                    break;

                case ilExteStatValue::TYPE_TEXT:
                default:
                    $align = ilExteStatValue::ALIGN_LEFT;
            }
        }

        // comment and alert
        if ($this->show_comment && !empty($value->comment)) {
            $comment = $value->comment;
        }
        if ($value->alert != ilExteStatValue::ALERT_NONE) {
            $sign = $value->alert;
        } elseif ($comment !== null) {
            $sign = 'info';
        }

        // render cell
        switch ($align) {
            case ilExteStatValue::ALIGN_RIGHT:
                if ($sign !== null) {
                    $this->renderSign($template, $sign, 'ilExteStatSignRight', $comment);
                }
                if ($content !== null) {
                    $this->renderContent($template, $content, 'ilExteStatValueRight', $comment, $value->uncertain);
                }
                break;
            case ilExteStatValue::ALIGN_LEFT:
            default:
                if (!$content !== null) {
                    $this->renderContent($template, $content, 'ilExteStatValueLeft', $comment, $value->uncertain);
                }
                if ($sign !== null) {
                    $this->renderSign($template, $sign, 'ilExteStatSignLeft', $comment);
                }
                break;
        }
        return $template->get();
    }

    private function addHelpText(string $text): HelpTopic
    {
        $topic = md5($text);
        $this->help_texts[$topic] = $text;
        return new HelpTopic($topic);
    }

    public function getHelpText(HelpPurpose $purpose, HelpTopic ...$topics): array
    {
        return array_map(fn($topic) => $this->help_texts[$topic->get()] ?? '', $topics);
    }

    /**
     * Render the value content
     */
    protected function renderContent(ilTemplate $template, string $content, string $class, ?string $comment = null, bool $uncertain = false)
    {
        if ($uncertain) {
            $content = '<i>' . $content . '</i>';
        }

        if (!empty($comment)) {
            $content = $this->renderWithTooltip($content, $comment);
        }

        $template->setCurrentBlock('value');
        $template->setVariable('CONTENT', $content);
        $template->parseCurrentBlock();

        $template->setCurrentBlock('cell');
        $template->setVariable('CLASS', $class);
        $template->parseCurrentBlock();
    }

    /**
     * Render an alert sign or comment
     */
    protected function renderSign(ilTemplate $template, ?string $sign, string $class, ?string $comment = null)
    {
        $sign = match ($sign) {
            'info' => '<span class="text-muted">&#9432;</span>',
            'good' => '<span class="text-success glyphicon glyphicon-ok"</span>',
            'medium' => '<span class="text-warning glyphicon glyphicon-adjust"</span>',
            'bad' => '<span class="text-danger glyphicon glyphicon-exclamation-sign"</span>',
            'unknown' => '<span class="text-muted glyphicon glyphicon-question-sign"</span>',
            default => ''
        };

        if (!empty($comment)) {
            $sign = $this->renderWithTooltip($sign, $comment);
        }

        $template->setCurrentBlock('sign');
        $template->setVariable('SIGN', $sign);
        $template->parseCurrentBlock();

        $template->setCurrentBlock('cell');
        $template->setVariable('CLASS', $class);
        $template->parseCurrentBlock();
    }

    public function renderWithTooltip(string $content, string $tooltip): string
    {
        $tooltip_id = $this->js_binding->createId();
        $content_id = $this->js_binding->createId();

        $embedding = $this->tooltip_renderer->maybeGetTooltipEmbedding($this->addHelpText($tooltip));
        $component = $this->factory->legacy($content)->withAdditionalOnLoadCode($embedding[1]);
        $this->js_binding->addOnLoadCode($component->getOnLoadCode()($content_id));

        $tpl = $this->plugin->getTemplate('tpl.il_exte_stat_tooltip.html');
        $tpl->setVariable('CONTENT', $this->renderer->render($component));
        $tpl->setVariable("ARIA_DESCRIBED_BY", $tooltip_id);
        $tpl->setVariable("ID", $content_id);

        return $embedding[0]($tooltip_id, $tpl->get());
    }

    /**
     * Get legend data
     * @return array	[ ['value' => ilExteStatValue, 'description' => string], ...]
     */
    public function getLegendData(): array
    {
        $data = array(
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_TEXT, 0, '', ilExteStatValue::ALERT_GOOD),
                'description' => $this->plugin->txt('legend_alert_good')
            ),
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_TEXT, 0, '', ilExteStatValue::ALERT_MEDIUM),
                'description' => $this->plugin->txt('legend_alert_medium')
            ),
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_TEXT, 0, '', ilExteStatValue::ALERT_BAD),
                'description' => $this->plugin->txt('legend_alert_bad')
            ),
            array(
                'value' => ilExteStatValue::_create('', ilExteStatValue::TYPE_TEXT, 0, '', ilExteStatValue::ALERT_UNKNOWN),
                'description' => $this->plugin->txt('legend_alert_unknown')
            ),
            array(
                'value' => ilExteStatValue::_create($this->lng->txt('value'), ilExteStatValue::TYPE_TEXT, 0, $this->lng->txt('comment'), ilExteStatValue::ALERT_NONE, true),
                'description' => $this->plugin->txt('legend_uncertain')
            ),
            array(
                'value' => ilExteStatValue::_create($this->lng->txt('value'), ilExteStatValue::TYPE_TEXT, 0, $this->lng->txt('comment')),
                'description' => $this->plugin->txt('legend_comment')
            )
        );

        return $data;
    }


    /**
     * Prepare a string value to be displayed in HTML
     */
    protected function textDisplay(string $text): string
    {
        // these would be deleted by the template engine
        $text = str_replace('{', '&#123;', $text);
        $text = str_replace('}', '&#125;', $text);

        $text = preg_replace('/<span class="latex">(.*)<\/span>/', '[tex]$1[/tex]', $text);
        $text = ilUtil::secureString($text, false);

        if (strpos($text, '[tex]') !== false) {
            $text = ilMathJax::getInstance()->insertLatexImages($text);
        }

        return $text;
    }

}
