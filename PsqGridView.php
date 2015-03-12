<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\grid;

use Yii;
use Closure;
use yii\i18n\Formatter;
use yii\base\InvalidConfigException;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\BaseListView;
use yii\base\Model;

/**
 * The PsqGridView widget is a generic customized grid.
 *
 */
class PsqGridView extends BaseListView
{
    const FILTER_POS_HEADER = 'header';
    const FILTER_POS_FOOTER = 'footer';
    const FILTER_POS_BODY = 'body';

    public $dataColumnClass;
    
    public $caption;
    
    public $captionOptions = [];
    
    public $tableOptions = ['class' => 'table dataTable'];
    
    public $options = ['class' => 'row'];
    
    public $headerRowOptions = [];
    
    public $footerRowOptions = [];
    
    public $rowOptions = [];
    
    public $beforeRow;
    
    public $afterRow;
    /**
     * @var boolean whether to show the header section of the grid table.
     */
    public $showHeader = true;
    /**
     * @var boolean whether to show the footer section of the grid table.
     */
    public $showFooter = false;
    /**
     * @var boolean whether to show the grid view if [[dataProvider]] returns no data.
     */
    public $showOnEmpty = true;
    /**
     * @var array|Formatter the formatter used to format model attribute values into displayable texts.
     * This can be either an instance of [[Formatter]] or an configuration array for creating the [[Formatter]]
     * instance. If this property is not set, the "formatter" application component will be used.
     */
    public $formatter;
    
    public $columns = [];
    /**
     * @var string the HTML display when the content of a cell is empty
     */
    public $emptyCell = '&nbsp;';

    public $filterModel;
    
    public $filterUrl;
    /**
     * @var string additional jQuery selector for selecting filter input fields
     */
    public $filterSelector;
    /**
     * @var string whether the filters should be displayed in the grid view. Valid values include:
     *
     * - [[FILTER_POS_HEADER]]: the filters will be displayed on top of each column's header cell.
     * - [[FILTER_POS_BODY]]: the filters will be displayed right below each column's header cell.
     * - [[FILTER_POS_FOOTER]]: the filters will be displayed below each column's footer cell.
     */
    public $filterPosition = self::FILTER_POS_BODY;
    /**
     * @var array the HTML attributes for the filter row element.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $filterRowOptions = ['class' => 'filters'];
    /**
     * @var array the options for rendering the filter error summary.
     * Please refer to [[Html::errorSummary()]] for more details about how to specify the options.
     * @see renderErrors()
     */
    public $filterErrorSummaryOptions = ['class' => 'error-summary'];
    /**
     * @var array the options for rendering every filter error message.
     * This is mainly used by [[Html::error()]] when rendering an error message next to every filter input field.
     */
    public $filterErrorOptions = ['class' => 'help-block'];
    /**
     * @var string the layout that determines how different sections of the list view should be organized.
     * The following tokens will be replaced with the corresponding section contents:
     *
     * - `{summary}`: the summary section. See [[renderSummary()]].
     * - `{errors}`: the filter model error summary. See [[renderErrors()]].
     * - `{items}`: the list items. See [[renderItems()]].
     * - `{sorter}`: the sorter. See [[renderSorter()]].
     * - `{pager}`: the pager. See [[renderPager()]].
     */
    // public $layout = "{summary}\n{items}\n{pager}";
    public $layout = "{items}\n{summary}\n{pager}";


    /**
     * Initializes the grid view.
     * This method will initialize required property values and instantiate [[columns]] objects.
     */
    public function init()
    {
        parent::init();
        if ($this->formatter == null) {
            $this->formatter = Yii::$app->getFormatter();
        } elseif (is_array($this->formatter)) {
            $this->formatter = Yii::createObject($this->formatter);
        }
        if (!$this->formatter instanceof Formatter) {
            throw new InvalidConfigException('The "formatter" property must be either a Format object or a configuration array.');
        }
        if (!isset($this->filterRowOptions['id'])) {
            $this->filterRowOptions['id'] = $this->options['id'] . '-filters';
        }

        $this->initColumns();
    }

    /**
     * Runs the widget.
     */
    public function run()
    {
        $id = $this->options['id'];
        $options = Json::encode($this->getClientOptions());
        $view = $this->getView();
        GridViewAsset::register($view);
        $view->registerJs("jQuery('#$id').yiiGridView($options);");
        parent::run();
    }

    /**
     * Renders validator errors of filter model.
     * @return string the rendering result.
     */
    public function renderErrors()
    {
        if ($this->filterModel instanceof Model && $this->filterModel->hasErrors()) {
            return Html::errorSummary($this->filterModel, $this->filterErrorSummaryOptions);
        } else {
            return '';
        }
    }

    public $summaryOptions = ['class' => 'summary dataTables_info'];

    public function renderSummary()
    {
        $count = $this->dataProvider->getCount();
        if ($count <= 0) {
            return '';
        }
        $tag = yii\helpers\ArrayHelper::remove($this->summaryOptions, 'tag', 'div');
        if (($pagination = $this->dataProvider->getPagination()) !== false) {
            $totalCount = $this->dataProvider->getTotalCount();
            $begin = $pagination->getPage() * $pagination->pageSize + 1;
            $end = $begin + $count - 1;
            if ($begin > $end) {
                $begin = $end;
            }
            $page = $pagination->getPage() + 1;
            $pageCount = $pagination->pageCount;
            if (($summaryContent = $this->summary) === null) {
                return Html::tag($tag, Yii::t('yii', 'Showing <b>{begin, number}-{end, number}</b> of <b>{totalCount, number}</b> {totalCount, plural, one{entry} other{entries}}', [
                        'begin' => $begin,
                        'end' => $end,
                        'count' => $count,
                        'totalCount' => $totalCount,
                        'page' => $page,
                        'pageCount' => $pageCount,
                    ]), $this->summaryOptions);
            }
        } else {
            $begin = $page = $pageCount = 1;
            $end = $totalCount = $count;
            if (($summaryContent = $this->summary) === null) {
                return Html::tag($tag, Yii::t('yii', 'Total <b>{count, number}</b> {count, plural, one{entry} other{entries}}', [
                    'begin' => $begin,
                    'end' => $end,
                    'count' => $count,
                    'totalCount' => $totalCount,
                    'page' => $page,
                    'pageCount' => $pageCount,
                ]), $this->summaryOptions);
            }
        }

        return Yii::$app->getI18n()->format($summaryContent, [
            'begin' => $begin,
            'end' => $end,
            'count' => $count,
            'totalCount' => $totalCount,
            'page' => $page,
            'pageCount' => $pageCount,
        ], Yii::$app->language);
    }

    /**
     * @inheritdoc
     */
    public function renderSection($name)
    {
        switch ($name) {
            case "{errors}":
                return $this->renderErrors();
            default:
                return parent::renderSection($name);
        }
    }

    /**
     * Returns the options for the grid view JS widget.
     * @return array the options
     */
    protected function getClientOptions()
    {
        $filterUrl = isset($this->filterUrl) ? $this->filterUrl : Yii::$app->request->url;
        $id = $this->filterRowOptions['id'];
        $filterSelector = "#$id input, #$id select";
        if (isset($this->filterSelector)) {
            $filterSelector .= ', ' . $this->filterSelector;
        }

        return [
            'filterUrl' => Url::to($filterUrl),
            'filterSelector' => $filterSelector,
        ];
    }

    /**
     * Renders the data models for the grid view.
     */
    public function renderItems()
    {
        $caption = $this->renderCaption();
        $columnGroup = $this->renderColumnGroup();
        $tableHeader = $this->showHeader ? $this->renderTableHeader() : false;
        $tableBody = $this->renderTableBody();
        $tableFooter = $this->showFooter ? $this->renderTableFooter() : false;
        $content = array_filter([
            $caption,
            $columnGroup,
            $tableHeader,
            $tableFooter,
            $tableBody,
        ]);

        return Html::tag('table', implode("\n", $content), $this->tableOptions);
    }

    /**
     * Renders the caption element.
     * @return bool|string the rendered caption element or `false` if no caption element should be rendered.
     */
    public function renderCaption()
    {
        if (!empty($this->caption)) {
            return Html::tag('caption', $this->caption, $this->captionOptions);
        } else {
            return false;
        }
    }

    /**
     * Renders the column group HTML.
     * @return bool|string the column group HTML or `false` if no column group should be rendered.
     */
    public function renderColumnGroup()
    {
        $requireColumnGroup = false;
        foreach ($this->columns as $column) {
            /* @var $column Column */
            if (!empty($column->options)) {
                $requireColumnGroup = true;
                break;
            }
        }
        if ($requireColumnGroup) {
            $cols = [];
            foreach ($this->columns as $column) {
                $cols[] = Html::tag('col', '', $column->options);
            }

            return Html::tag('colgroup', implode("\n", $cols));
        } else {
            return false;
        }
    }

    /**
     * Renders the table header.
     * @return string the rendering result.
     */
    public function renderTableHeader()
    {
        $cells = [];
        foreach ($this->columns as $column) {
            /* @var $column Column */
            $cells[] = $column->renderHeaderCell();
        }
        $content = Html::tag('tr', implode('', $cells), $this->headerRowOptions);
        if ($this->filterPosition == self::FILTER_POS_HEADER) {
            $content = $this->renderFilters() . $content;
        } elseif ($this->filterPosition == self::FILTER_POS_BODY) {
            $content .= $this->renderFilters();
        }

        return "<thead>\n" . $content . "\n</thead>";
    }

    /**
     * Renders the table footer.
     * @return string the rendering result.
     */
    public function renderTableFooter()
    {
        $cells = [];
        foreach ($this->columns as $column) {
            /* @var $column Column */
            $cells[] = $column->renderFooterCell();
        }
        $content = Html::tag('tr', implode('', $cells), $this->footerRowOptions);
        if ($this->filterPosition == self::FILTER_POS_FOOTER) {
            $content .= $this->renderFilters();
        }

        return "<tfoot>\n" . $content . "\n</tfoot>";
    }

    /**
     * Renders the filter.
     * @return string the rendering result.
     */
    public function renderFilters()
    {
        if ($this->filterModel !== null) {
            $cells = [];
            foreach ($this->columns as $column) {
                /* @var $column Column */
                $cells[] = $column->renderFilterCell();
            }

            return Html::tag('tr', implode('', $cells), $this->filterRowOptions);
        } else {
            return '';
        }
    }

    /**
     * Renders the table body.
     * @return string the rendering result.
     */
    public function renderTableBody()
    {
        $models = array_values($this->dataProvider->getModels());
        $keys = $this->dataProvider->getKeys();
        $rows = [];
        foreach ($models as $index => $model) {
            $key = $keys[$index];
            if ($this->beforeRow !== null) {
                $row = call_user_func($this->beforeRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }

            $rows[] = $this->renderTableRow($model, $key, $index);

            if ($this->afterRow !== null) {
                $row = call_user_func($this->afterRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
        }

        if (empty($rows)) {
            $colspan = count($this->columns);

            return "<tbody>\n<tr><td colspan=\"$colspan\">" . $this->renderEmpty() . "</td></tr>\n</tbody>";
        } else {
            return "<tbody>\n" . implode("\n", $rows) . "\n</tbody>";
        }
    }

    /**
     * Renders a table row with the given data model and key.
     * @param mixed $model the data model to be rendered
     * @param mixed $key the key associated with the data model
     * @param integer $index the zero-based index of the data model among the model array returned by [[dataProvider]].
     * @return string the rendering result
     */
    public function renderTableRow($model, $key, $index)
    {
        $cells = [];
        /* @var $column Column */
        foreach ($this->columns as $column) {
            $cells[] = $column->renderDataCell($model, $key, $index);
        }
        if ($this->rowOptions instanceof Closure) {
            $options = call_user_func($this->rowOptions, $model, $key, $index, $this);
        } else {
            $options = $this->rowOptions;
        }
        $options['data-key'] = is_array($key) ? json_encode($key) : (string) $key;

        return Html::tag('tr', implode('', $cells), $options);
    }

    /**
     * Creates column objects and initializes them.
     */
    protected function initColumns()
    {
        if (empty($this->columns)) {
            $this->guessColumns();
        }
        foreach ($this->columns as $i => $column) {
            if (is_string($column)) {
                $column = $this->createDataColumn($column);
            } else {
                $column = Yii::createObject(array_merge([
                    'class' => $this->dataColumnClass ? : DataColumn::className(),
                    'grid' => $this,
                ], $column));
            }
            if (!$column->visible) {
                unset($this->columns[$i]);
                continue;
            }
            $this->columns[$i] = $column;
        }
    }

    /**
     * Creates a [[DataColumn]] object based on a string in the format of "attribute:format:label".
     * @param string $text the column specification string
     * @return DataColumn the column instance
     * @throws InvalidConfigException if the column specification is invalid
     */
    protected function createDataColumn($text)
    {
        if (!preg_match('/^([^:]+)(:(\w*))?(:(.*))?$/', $text, $matches)) {
            throw new InvalidConfigException('The column must be specified in the format of "attribute", "attribute:format" or "attribute:format:label"');
        }

        return Yii::createObject([
            'class' => $this->dataColumnClass ? : DataColumn::className(),
            'grid' => $this,
            'attribute' => $matches[1],
            'format' => isset($matches[3]) ? $matches[3] : 'text',
            'label' => isset($matches[5]) ? $matches[5] : null,
        ]);
    }

    /**
     * This function tries to guess the columns to show from the given data
     * if [[columns]] are not explicitly specified.
     */
    protected function guessColumns()
    {
        $models = $this->dataProvider->getModels();
        $model = reset($models);
        if (is_array($model) || is_object($model)) {
            foreach ($model as $name => $value) {
                $this->columns[] = $name;
            }
        }
    }
}
