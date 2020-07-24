{% extends 'templates/layer.volt' %}

{% block content %}

    {% set update_url = url({'for':'web.review.update','id':review.id}) %}

    <form class="layui-form review-form" method="post" action="{{ update_url }}">
        <div class="layui-form-item mb0">
            <label class="layui-form-label">内容实用</label>
            <div class="layui-input-block">
                <div id="rating1"></div>
            </div>
        </div>
        <div class="layui-form-item mb0">
            <label class="layui-form-label">通俗易懂</label>
            <div class="layui-input-block">
                <div id="rating2"></div>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">逻辑清晰</label>
            <div class="layui-input-block">
                <div id="rating3"></div>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">评价内容</label>
            <div class="layui-input-block">
                <textarea name="content" class="layui-textarea">{{ review.content }}</textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label"></label>
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit="true" lay-filter="go">提交</button>
                <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                <input type="hidden" name="rating1" value="{{ review.rating1 }}">
                <input type="hidden" name="rating2" value="{{ review.rating2 }}">
                <input type="hidden" name="rating3" value="{{ review.rating3 }}">
            </div>
        </div>
    </form>
{% endblock %}

{% block include_js %}

    {{ js_include('web/js/my.review.js') }}

{% endblock %}