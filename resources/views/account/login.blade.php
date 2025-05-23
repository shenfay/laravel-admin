@extends('admin::layouts.default')

@section('style')
<link href="{{ asset('admin/theme/css/login.css') }}" rel="stylesheet">
@stop

@section('body')
<div class="login-container">

    <!-- 动态云层动画 开始 -->
    <div class="clouds-container">
        <div class="clouds clouds-footer"></div>
        <div class="clouds"></div>
        <div class="clouds clouds-fast"></div>
    </div>
    <!-- 动态云层动画 结束 -->

    <!-- 顶部导航条 开始 -->
    <div class="header notselect">
        <span class="title notselect">{{ settings('app_name') }} <sup>{{ settings('app_version') }}</sup></span>
        <ul>
            <li><a href="javascript:void(0)" target="_blank">帮助</a></li>
            <li>
                <a href="https://www.google.cn/intl/zh-CN/chrome/">推荐使用谷歌浏览器</a>
            </li>
        </ul>
    </div>
    <!-- 顶部导航条 结束 -->

    <!-- 页面表单主体 开始 -->
    <div class="container">
        <form autocomplete="off" method="post" class="content layui-form animated fadeInDown">
            <div class="people">
                <div class="tou"></div>
                <div id="left-hander" class="initial_left_hand transition"></div>
                <div id="right-hander" class="initial_right_hand transition"></div>
            </div>
            <ul>
                <li class="username">
                    <i></i>
                    <input required pattern="^\S{4,}$" name="phone" type="text" autofocus="autofocus" autocomplete="off" title="请输入4位及以上的字符" placeholder="请输入手机号码">
                </li>
                <li class="password">
                    <i></i>
                    <input required pattern="^\S{4,}$" name="password" type="password" autocomplete="off" title="请输入4位及以上的字符" placeholder="请输入密码">
                </li>
                <li class="remember">
                    <input class="form-check-input" style="display: block" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label" for="remember">记住我</label>
                </li>
                <li class="text-center">
                	{{ csrf_field() }}
                    <button type="submit" class="layui-btn">立 即 登 入</button>
                </li>
            </ul>
        </form>
    </div>
    <!-- 页面表单主体 结束 -->

    <!-- 底部版权信息 开始 -->
    <div class="footer notselect">
        {{ settings('site_copy') }}
    </div>
    <!--{/if}-->
    <!-- 底部版本信息 结束 -->

</div>
@stop

@section('script')
<script>
    if (window.location.href.indexOf('#') > -1) {
        window.location.href = window.location.href.split('#')[0];
    }
    $(function () {
        $('[name="password"]').on('focus', function () {
            $('#left-hander').removeClass('initial_left_hand').addClass('left_hand');
            $('#right-hander').removeClass('initial_right_hand').addClass('right_hand')
        }).on('blur', function () {
            $('#left-hander').addClass('initial_left_hand').removeClass('left_hand');
            $('#right-hander').addClass('initial_right_hand').removeClass('right_hand')
        });
    });
</script>
@stop