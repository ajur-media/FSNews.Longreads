<div class="box">
    <h2>Лонгриды (всего: {$longreads|@count})</h2>
    <button id="showhidden" class="edit">Показать / скрыть скрытые</button>
    <button id="showdescr" class="edit">Показать / скрыть описания</button>
    <button id="refresh" class="edit" style="cursor: pointer;">Проверить обновления</button>
    <div id="console"></div>
    <br><br>
    <span class="hint">
		Помните, что после загрузки списка существующих лонгридов с тильды (кнопка "проверить обновления") первыми в списке ниже идут лонгриды, "выгруженные" на сайт. А все остальные лонгриды (не выгруженные)
		находятся ниже и отмечены серым цветом.
	</span>
    <br>
    <table border="1" id="longreads">
        <thead>
        <tr>
            <th><b>Project</b></th>
            <th width="10%"><b>Дата</b></th>
            <th width="60%"><b>Заголовок</b></th>
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
                        <button data-id="{$longread.id}" class="pull-right update" style="display: none;">Обновить</button>
                        <button data-id="{$longread.id}" class="pull-right delete" style="display: none;">Удалить</button>
                        <button data-id="{$longread.id}" class="pull-right toshow" style="display: none;">Вернуть</button>
                        <button data-id="{$longread.id}" class="pull-right import" style="display: none;">Импорт</button>
                        <button data-id="{$longread.id}" class="pull-right tohide" style="display: none;">Скрыть</button>
                        <div class="import-block" style="display: none;">
                            <button data-id="{$longread.id}" class="cancel">Отмена</button>
                            <button data-id="{$longread.id}" class="start">Начать импорт</button>
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
    var domain = '{$domain_site_default}';
    var longreads = {
    {foreach from=$longreads item=longread}
    {$longread.id}: '{$longread.date}',
    {/foreach}
    }
    var showhidden = false;
    var tildapages;
</script>
<script type="text/javascript" src="/frontend/js/admin/longreads.js"></script>