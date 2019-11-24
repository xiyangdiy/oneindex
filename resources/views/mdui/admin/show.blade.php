@extends('mdui.layouts.admin')
@section('content')
    <div class="mdui-container-fluid mdui-m-t-2 mdui-m-b-2">

        <div class="mdui-typo">
            <h1>展示设置
                <small>前台显示的文件后缀, 空格隔开</small>
            </h1>
        </div>
        <form action="" method="post">
            @csrf
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label" for="image">图片</label>
                <input type="text" class="mdui-textfield-input" id="image" name="image"
                       value="{{ \App\Helpers\Tool::config('image','') }}">
            </div>
            <br>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label" for="video">视频</label>
                <input type="text" class="mdui-textfield-input" id="video" name="video"
                       value="{{ \App\Helpers\Tool::config('video','') }}">
            </div>
            <br>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label" for="dash">dash流</label>
                <input type="text" class="mdui-textfield-input" id="dash" name="dash"
                       value="{{ \App\Helpers\Tool::config('dash','') }}">
                <div class="mdui-textfield-helper">仅支持企业、教育版</div>
            </div>
            <br>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label" for="doc">文档</label>
                <input type="text" class="mdui-textfield-input" id="doc" name="doc"
                       value="{{ \App\Helpers\Tool::config('doc','') }}">
            </div>
            <br>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label" for="code">代码</label>
                <input type="text" class="mdui-textfield-input" id="code" name="code"
                       value="{{ \App\Helpers\Tool::config('code','') }}">
            </div>
            <br>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label" for="stream">文本</label>
                <input type="text" class="mdui-textfield-input" id="stream" name="stream"
                       value="{{ \App\Helpers\Tool::config('stream','') }}">
            </div>
            <br>
            <div class="mdui-textfield mdui-textfield-floating-label">
                <label class="mdui-textfield-label" for="name">图片</label>
                <input type="text" class="mdui-textfield-input" id="name" name="name"
                       value="{{ \App\Helpers\Tool::config('name','') }}">
            </div>
            <br>


            <button class="mdui-btn mdui-color-theme-accent mdui-ripple mdui-float-right" type="submit"><i
                    class="mdui-icon material-icons">check</i> 保存
            </button>
        </form>
    </div>
@stop
