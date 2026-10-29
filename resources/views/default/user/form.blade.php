@extends(theme('layout.layout'))

@section('container')
  <div class="container theme-showcase" role="main">
    <div class="row">
      <div class="col-md-12">
        @php
          $isEdit = isset($user);
          $action = $isEdit ? route('user_update', ['name' => $user->name]) : route('user_create');
          $rawLogin = $isEdit ? $user->getRawOriginal('lastlogin') : null;
          $never = empty($rawLogin) || str_starts_with((string) $rawLogin, '0000-00-00');
        @endphp

        @if ($isEdit)
          {!! Form::model($user, ['url' => $action, 'method' => 'POST']) !!}
        @else
          {!! Form::open(['url' => $action, 'method' => 'POST']) !!}
        @endif

        @if ($errors->any())
          <div class="alert alert-danger text-left" style="width:90%;padding:5px 10px;margin:0 auto 10px auto" role="alert">
            <ul style="margin-bottom:0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @elseif (isset($_GET['update']))
          <div class="alert alert-success text-left" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
            <strong>修改成功！</strong>
          </div>
        @endif

        <h4 style="width:90%;margin:6px auto 12px auto">{{ $title }}</h4>

        <table class="table text-left" style="width:90%;margin:0 auto;">
          <tr>
            <td style="width:18%;vertical-align:middle">{!! Form::label('name', '登录名: ') !!}</td>
            <td style="width:38%">
              {!! Form::text('name', null, ['class' => 'form-control', 'maxlength' => 32]) !!}
            </td>
            <td class="text-muted" style="vertical-align:middle">
              登录用，字母/数字/下划线，全局唯一
            </td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('nick', '昵称: ') !!}</td>
            <td>{!! Form::text('nick', null, ['class' => 'form-control', 'maxlength' => 64]) !!}</td>
            <td class="text-muted" style="vertical-align:middle">显示在右上角，留空则显示登录名</td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('email', '邮箱: ') !!}</td>
            <td>{!! Form::email('email', null, ['class' => 'form-control', 'maxlength' => 255]) !!}</td>
            <td class="text-muted" style="vertical-align:middle">全局唯一</td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('company', '公司: ') !!}</td>
            <td>{!! Form::text('company', null, ['class' => 'form-control', 'maxlength' => 64]) !!}</td>
            <td></td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('level', '状态: ') !!}</td>
            <td>
              {!! Form::select('level', ['1' => '启用（可登录）', '0' => '停用（禁止登录）'], $isEdit ? (string) (int) $user->level : '1', ['class' => 'form-control']) !!}
            </td>
            <td class="text-muted" style="vertical-align:middle">只有启用状态可以登录系统</td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('password', '密码: ') !!}</td>
            <td>{!! Form::password('password', ['class' => 'form-control']) !!}</td>
            <td class="text-muted" style="vertical-align:middle">
              @if ($isEdit)
                留空则不修改；至少 6 位
              @else
                至少 6 位
              @endif
            </td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('password_confirmation', '确认密码: ') !!}</td>
            <td>{!! Form::password('password_confirmation', ['class' => 'form-control']) !!}</td>
            <td class="text-muted" style="vertical-align:middle">两次输入必须一致</td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('remark', '备注: ') !!}</td>
            <td>{!! Form::textarea('remark', null, ['rows' => 3, 'class' => 'form-control']) !!}</td>
            <td></td>
          </tr>

          @if ($isEdit)
            <tr>
              <td style="vertical-align:middle">最后登录:</td>
              <td>
                @if ($never)
                  <span class="text-muted">从未登录（本栏由系统维护，不可编辑）</span>
                @else
                  <span class="text-muted">{{ $rawLogin }} · {{ $user->lastip ?: '—' }}（系统维护，不可编辑）</span>
                @endif
              </td>
              <td></td>
            </tr>
          @endif

          <tr>
            <td></td>
            <td colspan="2">
              {!! Form::submit('保存', ['class' => 'btn btn-primary', 'style' => 'width:90px']) !!}
              <button type="button" onClick="location.href='{!! route('user_list') !!}'" class="btn btn-danger" style="width:90px;margin-left:8px">返回</button>
              @if ($isEdit)
                <span class="text-muted" style="margin-left:12px">
                  停用、删除、重置密码请在<a href="{{ route('user_list') }}">用户列表</a>里操作
                </span>
              @endif
            </td>
          </tr>
        </table>

        {!! Form::close() !!}
      </div>
    </div>
  </div>
@stop
