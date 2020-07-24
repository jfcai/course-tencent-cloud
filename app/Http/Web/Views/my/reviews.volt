{% extends 'templates/full.volt' %}

{% block content %}

    {{ partial('partials/macro_course') }}

    <div class="layout-main">
        <div class="my-sidebar">{{ partial('my/menu') }}</div>
        <div class="my-content">
            <div class="wrap">
                <div class="my-nav-title">我的评价</div>
                {% if pager.total_pages > 0 %}
                    <table class="layui-table review-table">
                        <colgroup>
                            <col>
                            <col>
                            <col width="15%">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>内容</th>
                            <th>评分</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        {% for item in pager.items %}
                            {% set course_url = url({'for':'web.course.show','id':item.course.id}) %}
                            {% set edit_url = url({'for':'web.review.edit','id':item.id}) %}
                            {% set delete_url = url({'for':'web.review.delete','id':item.id}) %}
                            <tr>
                                <td>
                                    <p class="title layui-elip">课程：<a href="{{ course_url }}">{{ item.course.title }}</a></p>
                                    <p class="content layui-elip" title="{{ item.content|e }}">评价：{{ item.content }}</p>
                                </td>
                                <td>
                                    <p class="rating">内容实用：{{ star_info(item.rating1) }}</p>
                                    <p class="rating">通俗易懂：{{ star_info(item.rating2) }}</p>
                                    <p class="rating">逻辑清晰：{{ star_info(item.rating3) }}</p>
                                </td>
                                <td>
                                    <button class="layui-btn layui-btn-xs btn-edit-review" data-url="{{ edit_url }}">修改</button>
                                    <button class="layui-btn layui-btn-xs layui-bg-red kg-delete" data-url="{{ delete_url }}">删除</button>
                                </td>
                            </tr>
                        {% endfor %}
                        </tbody>
                    </table>
                    {{ partial('partials/pager') }}
                {% endif %}
            </div>
        </div>
    </div>

{% endblock %}

{% block include_js %}

    {{ js_include('web/js/my.js') }}

{% endblock %}