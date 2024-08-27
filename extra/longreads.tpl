<div class="box">
    <h2>Лонгриды (всего: {$longreads|@count})</h2>
    <button id="showhidden" class="longreads_action edit">Показать / скрыть скрытые</button>
    <button id="showdescr" class="longreads_action edit">Показать / скрыть описания</button>
    <button id="refresh" class="longreads_action edit" style="cursor: pointer;">Проверить обновления</button>
    <div id="console"></div>
    <br><br>
    <div class="hint">
		Помните, что после загрузки списка существующих лонгридов с тильды (кнопка "проверить обновления") первыми в списке ниже идут лонгриды, "выгруженные" на сайт. А все остальные лонгриды (не выгруженные)
		находятся ниже и отмечены серым цветом.
	</div>
    <br>
    <table border="1" id="longreads">
        <thead>
        <tr>
            <th><b>Project</b></th>
            <th width="10%"><b>Дата</b></th>
            <th width="60%" style="text-align: center"><b>Заголовок</b></th>
            <th width="30%">&nbsp;</th>
        </tr>
        </thead>
        <tbody>
        {if $longreads|@count > 0}
            {foreach from=$longreads item=longread}
                <tr id="row-{$longread.id}"
                    data-status="{$longread.status}"
                    data-id="{$longread.id}"
                    data-projectid="{$longread.projectid}"
                    class="{if $longread.status eq -1}deleted-row{/if}"
                    style="{if $longread.status eq -1}display:none;{/if}">

                    <td align="center">{$longread.projectid}</td>
                    <td align="center" class="date">{$longread.date}<span class="change"></span></td>
                    <td>
                        <p class="pagetitle">
                            {if $longread.status eq 1}
                                <a href='{$domain_site_default}/longreads/{$longread.folder}/' target='_blank'
                                   title='Открыть в новом окне'>{$longread.title}</a>
                            {else}
                                {$longread.title}
                            {/if}
                        </p>
                        <p class="description">{$longread.descr}</p>
                    </td>
                    <td class="buttons">
                        <button data-id="{$longread.id}" class="longreads_action pull-right update" style="display: none;">Обновить</button>
                        <button data-id="{$longread.id}" class="longreads_action pull-right delete" style="display: none;">Удалить</button>
                        <button data-id="{$longread.id}" class="longreads_action pull-right toshow" style="display: none;">Вернуть</button>
                        <button data-id="{$longread.id}" class="longreads_action pull-right import" style="display: none;">Импорт</button>
                        <button data-id="{$longread.id}" class="longreads_action pull-right tohide" style="display: none;">Скрыть</button>
                        <div class="import-block" style="display: none;">
                            <button data-id="{$longread.id}" class="longreads_action cancel">Отмена</button>
                            <button data-id="{$longread.id}" class="longreads_action start">Начать импорт</button>
                            <input class="folder" type="text" value="{$longread.folder}" placeholder="URL по которому будет доступен лонгрид">
                        </div>
                    </td>
                </tr>
            {/foreach}
        {else}
            <tr>
                <td colspan=3>Нет импортированных страниц</td>
            </tr>
        {/if}
        </tbody>
    </table>
</div>
<script type="text/javascript">
    const domain = '{$domain_site_default}';
    let longreads = {
        {foreach from=$longreads item=longread}
        {$longread.id}: '{$longread.date}',
        {/foreach}
    };
    window.showhidden = false;
    let tildapages;
</script>
<script type="text/javascript" src="/frontend/js/admin/longreads.js"></script>
<style>
    .longreads_action {
        display: block;
        padding: 1px 8px;
        color: #fff !important;
        border-radius: 2px;
        margin: 0 2px;
        text-transform: uppercase;
        font-size: 0.9em;
        cursor: pointer;
        min-width: 65px;
    }
</style>