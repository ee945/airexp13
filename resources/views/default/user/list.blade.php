@extends(theme('layout.layout'))

@section('container')
  <div class="container theme-showcase" role="main">

    @if (session('error'))
      <div class="alert alert-danger" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
        <strong>{{ session('error') }}</strong>
      </div>
    @endif

    @isset($addno)
      <div class="alert alert-success" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
        <strong>添加成功：&nbsp;<a href="{{ route('user_view', ['name' => $addno]) }}">{{ $addno }}</a>&nbsp;已创建</strong>
      </div>
    @endisset

    @isset($missing)
      <div class="alert alert-danger" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
        <strong>未找到账号：&nbsp;{{ $missing }}</strong>
      </div>
    @endisset

    @isset($del)
      @if ($del == 'yes')
        <div class="alert alert-warning" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
          <strong>删除成功：&nbsp;{{ $delno }}&nbsp;已删除</strong>
        </div>
      @else
        <div class="alert alert-danger" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
          <strong>删除失败：&nbsp;{{ $delno ?? '' }}&nbsp;不存在</strong>
        </div>
      @endif
    @endisset

    @isset($status)
      <div class="alert alert-info" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
        @if ($status == 'on')
          <strong>{{ $statusno }}&nbsp;已启用，现在可以登录系统。</strong>
        @else
          <strong>{{ $statusno }}&nbsp;已停用，将无法登录系统。</strong>
        @endif
      </div>
    @endisset

    @isset($resetno)
      <div class="alert alert-warning" style="width:90%;padding:8px 15px;margin:0 auto 10px auto" role="alert">
        <strong>{{ $resetno }}&nbsp;的密码已重置为：<code>{{ session('resetpwd') }}</code>（只显示这一次，请立即告知本人）</strong>
      </div>
    @endisset

    <div class="row">
      <div class="col-md-12">
        {!! Form::open(['method' => 'get', 'url' => route('user_list')]) !!}
        <table class="table table-condensed table-responsive" style="width:90%;margin:2px auto;">
          <tr>
            <td width="8%" style="vertical-align:middle">{!! Form::label('search_name', '登录名: ') !!}</td>
            <td width="12%">{!! Form::text('search_name', $search_name ?? null, ['class' => 'form-control']) !!}</td>
            <td width="7%" style="vertical-align:middle">{!! Form::label('search_nick', '昵称: ') !!}</td>
            <td width="10%">{!! Form::text('search_nick', $search_nick ?? null, ['class' => 'form-control']) !!}</td>
            <td width="7%" style="vertical-align:middle">{!! Form::label('search_email', '邮箱: ') !!}</td>
            <td width="12%">{!! Form::text('search_email', $search_email ?? null, ['class' => 'form-control']) !!}</td>
            <td width="7%" style="vertical-align:middle">{!! Form::label('search_company', '公司: ') !!}</td>
            <td width="9%">{!! Form::text('search_company', $search_company ?? null, ['class' => 'form-control']) !!}</td>
          </tr>
          <tr>
            <td style="vertical-align:middle">{!! Form::label('search_level', '状态: ') !!}</td>
            <td>
              {!! Form::select('search_level', ['' => '全部', '1' => '启用', '0' => '停用'], $search_level ?? null, ['class' => 'form-control']) !!}
            </td>
            <td style="vertical-align:middle">{!! Form::label('perpage', '每页: ') !!}</td>
            <td>{!! Form::text('perpage', $perpage ?? 20, ['class' => 'form-control']) !!}</td>
            <td colspan="2" class="text-right">
              {!! Form::submit('查询', ['class' => 'btn btn-primary form-control', 'style' => 'width:90px;display:inline-block']) !!}
              <button type="button" onclick="reset_search()" class="btn btn-default" style="width:90px">重置</button>
            </td>
            <td colspan="2" class="text-right">
              <button type="button" onClick="location.href='{!! route('user_add') !!}'" class="btn btn-success" style="width:110px">添加用户</button>
            </td>
          </tr>
        </table>
        {!! Form::close() !!}
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <p class="text-muted" style="width:90%;margin:6px auto 4px auto">
          共 <strong>{{ $total }}</strong> 个账号，其中启用 <strong>{{ $enabled }}</strong> 个；
          只有「启用」状态的账号可以登录系统。
        </p>

        <table class="table table-striped table-hover table-bordered table-condensed table-responsive" style="width:90%;margin:0 auto;">
          <thead>
            <tr>
              <th class="text-center" width="13%">登录名</th>
              <th class="text-center" width="10%">昵称</th>
              <th class="text-center" width="20%">邮箱</th>
              <th class="text-center" width="8%">公司</th>
              <th class="text-center" width="7%">状态</th>
              <th class="text-center" width="15%">最后登录 / IP</th>
              <th class="text-center" width="9%">备注</th>
              <th class="text-center" width="18%">操作</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($users as $user)
              @php
                $rawLogin = $user->getRawOriginal('lastlogin');
                $never = empty($rawLogin) || str_starts_with((string) $rawLogin, '0000-00-00');
                $isSelf = auth()->id() === $user->id;
              @endphp
              <tr @if ($isSelf) class="info" @endif>
                <td>
                  <a href="{{ route('user_view', ['name' => $user->name]) }}">{{ $user->name }}</a>
                  @if ($isSelf)<span class="label label-primary">当前</span>@endif
                </td>
                <td>{{ $user->nick }}</td>
                <td class="text-break">{{ $user->email }}</td>
                <td>{{ $user->company }}</td>
                <td class="text-center">
                  @if ((int) $user->level === 1)
                    <span class="label label-success">启用</span>
                  @else
                    <span class="label label-default">停用</span>
                  @endif
                </td>
                <td class="text-center">
                  @if ($never)
                    <span class="text-muted">从未登录</span>
                  @else
                    <div>{{ $rawLogin }}</div>
                    <div class="text-muted" style="font-size:11px">{{ $user->lastip ?: '—' }}</div>
                  @endif
                </td>
                <td>{{ \Illuminate\Support\Str::limit($user->remark, 20) }}</td>
                <td class="text-center" style="white-space:nowrap">
                  <a href="{{ route('user_view', ['name' => $user->name]) }}" class="btn btn-xs btn-default">修改</a>

                  @if ($isSelf)
                    <span class="btn btn-xs btn-default disabled" title="不能停用当前登录的账号">停用</span>
                  @else
                    <a href="{{ route('user_status', ['name' => $user->name]) }}"
                       onclick="return confirm('确定{{ (int) $user->level === 1 ? '停用' : '启用' }}账号 “{{ $user->name }}” ？')"
                       class="btn btn-xs {{ (int) $user->level === 1 ? 'btn-warning' : 'btn-success' }}">
                      {{ (int) $user->level === 1 ? '停用' : '启用' }}
                    </a>
                  @endif

                  <form method="POST" action="{{ route('user_reset', ['name' => $user->name]) }}" style="display:inline"
                        onsubmit="return confirm('确定重置 “{{ $user->name }}” 的密码？系统会生成一个随机新密码。')">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-info">重置密码</button>
                  </form>

                  @if ($isSelf)
                    <span class="btn btn-xs btn-danger disabled" title="不能删除当前登录的账号">删除</span>
                  @else
                    <form method="POST" action="{{ route('user_del') }}" style="display:inline"
                          onsubmit="return confirm('确定删除账号 “{{ $user->name }}” ？删除后不可恢复。')">
                      @csrf
                      <input type="hidden" name="name" value="{{ $user->name }}">
                      <button type="submit" class="btn btn-xs btn-danger">删除</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center text-muted">没有符合条件的账号</td>
              </tr>
            @endforelse
          </tbody>
        </table>

        {{ $users->render() }}
      </div>
    </div>
  </div>

  <script>
    function reset_search() {
      ['search_name', 'search_nick', 'search_email', 'search_company'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { el.value = ''; }
      });
      var level = document.getElementById('search_level');
      if (level) { level.value = ''; }
      var perpage = document.getElementById('perpage');
      if (perpage) { perpage.value = '20'; }
    }
  </script>
@stop
