<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Form 构建器：替代已停止维护的 laravelcollective/html。
 *
 * 只实现原项目视图实际用到的子集，签名与 laravelcollective 保持一致：
 *   Form::open(['url' => ..., 'method' => 'POST', 'class' => ...])
 *   Form::text('name', $value, ['size' => 16])
 *   Form::label('name', '标签')
 *   Form::date / textarea / submit / checkbox / radio / select / hidden / password / file
 *   Form::close()
 *
 * 所有方法返回 HtmlString，视图里用 {!! !!} 输出。
 */
class Form
{
    /** @var array 未闭合的表单栈 */
    protected static array $stack = [];

    /** @var mixed 当前绑定到表单的模型（Form::model） */
    protected static mixed $model = null;

    /** @var array 模型绑定栈，支持嵌套表单 */
    protected static array $modelStack = [];

    /**
     * 打开表单。默认动作指向当前 URL、方法 POST。
     */
    public static function open(array $options = []): HtmlString
    {
        $method = strtoupper($options['method'] ?? 'POST');
        $action = $options['url'] ?? $options['action'] ?? request()->fullUrl();
        $files = ! empty($options['files']);
        $ajax = ! empty($options['ajax']);

        unset($options['url'], $options['action'], $options['method'], $options['files'], $options['ajax']);

        // GET 不需要伪造方法，其余一律 POST + _method 伪装
        $hidden = '';
        $formMethod = $method;
        if (! in_array($method, ['GET', 'POST'], true)) {
            $hidden = static::hidden('_method', $method)->toHtml();
            $formMethod = 'POST';
        }

        $attributes = static::attributes($options);

        $html = '<form method="'.$formMethod.'" action="'.e($action).'"'
            .($files ? ' enctype="multipart/form-data"' : '')
            .($ajax ? ' data-ajax="true"' : '')
            .' accept-charset="UTF-8"'.$attributes.'>'
            .csrf_field()
            .$hidden;

        static::$stack[] = $formMethod;

        return new HtmlString($html);
    }

    public static function close(): HtmlString
    {
        array_pop(static::$stack);
        // 还原上一层的模型绑定（嵌套表单安全）
        static::$model = static::$modelStack === [] ? null : array_pop(static::$modelStack);

        return new HtmlString('</form>');
    }

    /**
     * 绑定模型打开表单：字段未显式传值时自动取模型属性，
     * 与原 laravelcollective/html 的行为一致。
     */
    public static function model(mixed $model, array $options = []): HtmlString
    {
        static::$modelStack[] = static::$model;
        static::$model = $model;

        return static::open($options);
    }

    public static function label(string $name, ?string $value = null, array $options = []): HtmlString
    {
        $value = $value ?? str_replace(['_', '.'], ' ', ucfirst($name));
        // 原项目里存在 Form::label('x', '&nbsp;') 这类用法，保留实体不做二次转义
        $text = str_contains($value, '&') ? $value : e($value);

        return new HtmlString('<label for="'.e($name).'"'.static::attributes($options).'>'.$text.'</label>');
    }

    public static function text(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return static::input('text', $name, $value, $options);
    }

    public static function date(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return static::input('date', $name, $value, $options);
    }

    public static function email(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return static::input('email', $name, $value, $options);
    }

    public static function password(string $name, array $options = []): HtmlString
    {
        return static::input('password', $name, '', $options);
    }

    public static function hidden(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return static::input('hidden', $name, $value, $options);
    }

    public static function file(string $name, array $options = []): HtmlString
    {
        return static::input('file', $name, null, $options);
    }

    public static function number(string $name, mixed $value = null, array $options = []): HtmlString
    {
        return static::input('number', $name, $value, $options);
    }

    public static function textarea(string $name, mixed $value = null, array $options = []): HtmlString
    {
        $value = $value ?? static::value($name);
        $options['name'] = $name;
        $options['id'] = $options['id'] ?? static::id($name);

        return new HtmlString(
            '<textarea'.static::attributes($options).'>'.e($value).'</textarea>'
        );
    }

    public static function submit(string $value = 'Submit', array $options = []): HtmlString
    {
        return static::input('submit', null, $value, $options);
    }

    public static function button(string $value = 'Button', array $options = []): HtmlString
    {
        $options['type'] = 'button';

        return new HtmlString('<button'.static::attributes($options).'>'.e($value).'</button>');
    }

    public static function checkbox(string $name, mixed $value = 1, bool $checked = false, array $options = []): HtmlString
    {
        return static::checkable('checkbox', $name, $value, $checked, $options);
    }

    public static function radio(string $name, mixed $value = null, bool $checked = false, array $options = []): HtmlString
    {
        return static::checkable('radio', $name, $value, $checked, $options);
    }

    public static function select(
        string $name,
        array $list = [],
        mixed $selected = null,
        array $options = []
    ): HtmlString {
        $selected = is_null($selected) ? static::value($name) : $selected;
        $options['name'] = $name;
        $options['id'] = $options['id'] ?? static::id($name);

        $html = '<select'.static::attributes($options).'>';
        foreach ($list as $key => $display) {
            if (is_array($display)) {
                // 分组选项
                $html .= '<optgroup label="'.e($key).'">';
                foreach ($display as $groupKey => $groupDisplay) {
                    $html .= static::option($groupKey, $groupDisplay, $selected);
                }
                $html .= '</optgroup>';
            } else {
                $html .= static::option($key, $display, $selected);
            }
        }

        return new HtmlString($html.'</select>');
    }

    // ------------------------------------------------------------------

    protected static function option(mixed $key, mixed $display, mixed $selected): string
    {
        $isSelected = is_array($selected)
            ? in_array((string) $key, array_map('strval', $selected), true)
            : (string) $key === (string) $selected;

        return '<option value="'.e($key).'"'.($isSelected ? ' selected="selected"' : '').'>'
            .e($display).'</option>';
    }

    protected static function checkable(
        string $type,
        string $name,
        mixed $value,
        bool $checked,
        array $options
    ): HtmlString {
        $options['type'] = $type;
        $options['name'] = $name;
        $options['value'] = $value;

        if ($checked) {
            $options['checked'] = 'checked';
        }

        return new HtmlString('<input'.static::attributes($options).'>');
    }

    protected static function input(string $type, ?string $name, mixed $value, array $options): HtmlString
    {
        $options['type'] = $type;

        if ($name !== null) {
            $options['name'] = $name;
            $options['id'] = $options['id'] ?? static::id($name);
            $options['value'] = $value ?? static::value($name);
        } elseif ($type === 'submit' && ! isset($options['value'])) {
            $options['value'] = $value;
        }

        return new HtmlString('<input'.static::attributes($options).'>');
    }

    /**
     * 取字段当前值：上次提交 old() 优先，其次绑定模型的属性。
     * 未命中返回 null 而不是空串，以贴合原项目 "isset($x) ? $x : null" 的写法。
     */
    protected static function value(string $name): mixed
    {
        $old = old($name);
        if ($old !== null) {
            return $old;
        }

        if (static::$model !== null) {
            return data_get(static::$model, $name);
        }

        return null;
    }

    protected static function id(string $name): string
    {
        return str_replace(['[', ']', '.'], ['_', '', '_'], $name);
    }

    protected static function attributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $html = [];
        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $key = $value;
                $value = $key;
            }

            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $value = $key;
            }

            $html[] = e($key).'="'.e($value).'"';
        }

        return $html === [] ? '' : ' '.implode(' ', $html);
    }
}
