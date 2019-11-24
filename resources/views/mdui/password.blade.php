@extends('mdui.layouts.main')
@section('content')
    <div class="mdui-container-fluid">
        <div class="mdui-col-md-6 mdui-col-offset-md-3">
            <form action="{{ route('password') }}" method="post">
                @csrf
                <div class="mdui-textfield mdui-textfield-floating-label">
                    <i class="mdui-icon material-icons">https</i>
                    <label class="mdui-textfield-label" for="password">输入密码进行查看</label>
                    <input name="password" class="mdui-textfield-input" type="password" id="password" required/>
                    <input type="hidden" name="pass_id" value="{{ encrypt($pass_id) }}">
                    <input type="hidden" name="origin_path" value="{{ encrypt($origin_path) }}">
                </div>
                <br>
                <button type="submit" class="mdui-center mdui-btn mdui-btn-raised mdui-ripple mdui-color-theme">
                    <i class="mdui-icon material-icons">fingerprint</i>
                    查看
                </button>

            </form>
        </div>

    </div>
@stop
