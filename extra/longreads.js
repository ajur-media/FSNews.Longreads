/**
 * jQuery required
 */
$(function () {
    // Шаблон строки для новой страницы
    let template_pagerow = '\
            <tr id="row-%id%" data-status="0" data-id="%id%" class="new">\
                <td align="center">%projectid%</td>\
                <td align="center" class="date">%date%<span class="change"></span></td>\
                <td>\
                    <p class="pagetitle">%title%</p>\
                    <p class="description">%descr%</p>\
                </td>\
                <td class="buttons">\
                    <button data-id="%id%" class="pull-right update" style="display: none;">Обновить</button>\
                    <button data-id="%id%" class="pull-right delete"  style="display: none;">Удалить</button>\
                    <button data-id="%id%" class="pull-right toshow"  style="display: none;">Вернуть</button>\
                    <button data-id="%id%" class="pull-right import"  style="display: block;">Импорт</button>\
                    <button data-id="%id%" class="pull-right tohide"  style="display: block;">Скрыть</button>\
                    <div class="import-block" style="display: none;">\
                        <button data-id="%id%" class="cancel">Отмена</button>\
                        <button data-id="%id%" class="start">Начать импорт</button>\
                        <input class="folder" type="text" value="" placeholder="URL по которому будет доступен лонгрид">\
                    </div>\
                </td>\
            </tr>';

    /* =========== ФУНКЦИИ ============ */

    /**
     * Удалить страницу
     *
     * @param id
     */
    function page_delete(id) {
        disable_buttons();
        $.post('/longreads/delete/', {
            id: id
        }, function (response) {
            if (response === 'ok') {
                $(`#row-${id}`).remove();
                message('Страница успешно удалена', 'green');
            } else {
                message(response, 'red');
            }
            disable_buttons(false);
        });
    }

    /**
     * Скрыть/показать страницу
     *
     * @param id
     * @param toggle
     */
    function page_toggle(id, toggle) {
        disable_buttons();
        $.post('/longreads/page_toggle/', {
            id: id,
            toggle: toggle
        }, function (response) {
            if (response === 'ok') {
                hide_buttons(id);
                let row = $(`#row-${id}`);
                if (toggle === 'hide') {
                    if (!showhidden) row.hide();
                    row.addClass('deleted-row');
                    show_button(id, 'toshow');
                    message('Страница успешно скрыта', 'green');
                } else {
                    row.removeClass('deleted-row');
                    show_button(id, 'import');
                    show_button(id, 'tohide');
                    message('Страница успешно восстановлена из скрытых', 'green');
                }
            } else {
                message(response, 'red');
            }
            disable_buttons(false);
        });
    }

    /**
     * Показывает кнопки управления
     *
     * @param id
     * @param button
     */
    function show_button(id, button) {
        $(`#row-${id} button.${button}`).show();
    }

    /**
     * Скрывает все кнопки управления
     *
     * @param id
     */
    function hide_buttons(id) {
        $(`#row-${id} button`).hide();
        $(`#row-${id} .import-block button`).show();
    }

    /**
     * Импорт страницы
     *
     * @param id
     * @param folder
     * @param update
     */
    function page_import(id, folder, update = false) {
        message('Идет импорт страницы...');
        disable_buttons();
        $.post('/longreads/import/', {
            id: id,
            folder: folder, update: update
        }).complete(function (response) {
            if (response.responseText === 'ok') {
                hide_buttons(id);
                show_button(id, 'delete');
                show_button(id, 'update');
                message('Импорт страницы успешно завершен', 'green');
                let title = $(`#row-${id} p.pagetitle`);
                let title_text = title.text();
                title.html(`<a href="${domain}/longreads/${folder}/" target="_blank">${title.text()}</a>`);

                // title.html('<a href="' + domain + '/longreads/' + folder + '/" target="_blank">' + title.text() + '</a>');

            } else {
                message(response.responseText, 'red');
                hide_buttons(id);
                show_button(id, 'tohide');
                show_button(id, 'import');
            }
            $(`#row-${id} .loader`).remove();
            disable_buttons(false);
        });
    }

    /**
     * Message
     *
     * @param message
     * @param color
     */
    function message(message, color = 'black') {
        let t = Date.now();
        let span = `<span id="console_${t}" class="console" style="background:${color};">${message}</span>`;
        let $span_console = $(`span#console_${t}`);

        $('#console').html(span);

        if (color !== 'black') {
            $span_console.click(function () {
                $(this).remove()
            });

            setTimeout(function () {
                $span_console.remove();
            }, 3000);
        }
    }

    /**
     * Транслитерирует название страницы в название папки
     *
     * @param value
     * @returns {string}
     */
    function transliterate(value) {
        const arr = {
            ' ': '-',
            'а': 'a',
            'б': 'b',
            'в': 'v',
            'г': 'g',
            'д': 'd',
            'е': 'e',
            'ж': 'g',
            'з': 'z',
            'и': 'i',
            'й': 'y',
            'к': 'k',
            'л': 'l',
            'м': 'm',
            'н': 'n',
            'о': 'o',
            'п': 'p',
            'р': 'r',
            'с': 's',
            'т': 't',
            'у': 'u',
            'ф': 'f',
            'ы': 'i',
            'э': 'e',
            'ё': 'yo',
            'х': 'h',
            'ц': 'ts',
            'ч': 'ch',
            'ш': 'sh',
            'щ': 'shch',
            'ю': 'yu',
            'я': 'ya'
        };
        value = value.toLowerCase().trim().replace(/[^а-яёa-z\d\s]/giu, '').replace(/[ьъ]/gui, '');
        let replacer = function (a) {
            return arr[a] || a
        };
        return value.replace(/[а-яё\s]/g, replacer).replace(/--/, '-');
    }

    /**
     * Обновление списка страниц по Tilda API
     */
    function refresh() {
        message('Идет получение страниц по Tilda API...');
        disable_buttons();

        $.post('/longreads/get_tilda_pages_list/', { }, function (data) {

            // Если ответ от Tilda не получен
            if (data.status !== 'FOUND' || !data.result) {
                message('Ошибка получения данных Tilda API', 'red');
                disable_buttons(false);
                return;
            }

            let tildapages = data.result;
            let counttildapages = tildapages.length;
            let complete = true;
            let updated = false;

            data.result.forEach(function (page) {
                // Если страница уже в БД

                // longreads - глобальная переменная, создается в longreads.tpl
                if (page.id in window.longreads) {
                    // Проверяем дату обновения
                    if (longreads[page.id] !== page.date && $(`#row-${page.id}`).data('status') === 1) {
                        $(`#row-${page.id} td.date span.change`).html(page.date);
                        show_button(page.id, 'update');
                        updated = true;
                    }

                    counttildapages--;
                    if (counttildapages === 0 && complete) {
                        let msg = '';
                        if (counttildapages === 0) msg = 'Новых страниц нет';
                        if (updated) msg += '. Есть обновления.';
                        message(msg, 'green');
                        disable_buttons(false);
                    }
                } else {

                    // добавляем запись о лонгриде в базу
                    complete = false;
                    $.post('/longreads/page_add/', {
                        page: page
                    }, function (response) {
                        if (response === 'ok') {
                            message(`Добавление: "${page.title}"`);
                            $('#longreads tbody').prepend(
                                template_pagerow
                                    .replace('%id%', page.id)
                                    .replace('%id%', page.id)
                                    .replace('%title%', page.title)
                                    .replace('%date%', page.date)
                                    .replace('%descr%', page.descr)
                                    .replace('%projectid%', page.projectid)
                            );
                        } else if (response === 'update') {
                            message( 'Дубль', 'yellow')
                        } else {
                            message(response, 'red');
                        }
                        counttildapages--;
                        if (counttildapages === 0) {
                            message('Обновлено успешно', 'green');
                            disable_buttons(false);
                        }
                    }); // $.post
                } // if else
            }); // forEach
        }); // $.post '/longreads/get_tilda_pages_list/'
    } // fn refresh

    /**
     *
     * @param disabled
     */
    function disable_buttons(disabled = true) {
        if (disabled) {
            $('button').attr('disabled', 'disabled');
        } else {
            $('button').removeAttr('disabled');
        }
    }

    /* ==================== BIND ACTIONS =================== */
    $('button#showhidden').click(function (e) {
        e.preventDefault();
        showhidden = (!showhidden);
        $('.deleted-row').toggle();
        message((showhidden) ? 'Скрытые элементы показаны' : 'Скрытые элементы скрыты', 'green');
    });

    $('button#showdescr').click(function (e) {
        e.preventDefault();
        $('p.description').toggle();
    });

    $('button#refresh').click(function (e) {
        e.preventDefault();
        refresh();
    });

    // Инициируем кнопки
    $('#longreads tr').each(function () {
        let $this = $(this);
        let id = $this.data('id');
        switch ($this.data('status')) {
            case -1:
                show_button(id, 'toshow');
                break;
            case 1:
                show_button(id, 'update');
                show_button(id, 'delete');
                break;
            default:
                show_button(id, 'tohide');
                show_button(id, 'import');
        }

        // init_row_buttons(id);
    });
    bind_rows_buttons_actions();

    /**
     * === BIND row buttons actions ===
     */
    function bind_rows_buttons_actions() {
        $("tr button.import").click(function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            let row = $(this).parents('tr').first(); // найти контейнер <tr id="row-XXXX"> ....

            let title = row.find('p.pagetitle').text().trim();

            row.find('.folder').val(transliterate(title));
            row.find('.import-block').show();

            hide_buttons(id);
        });

        $("tr button.update").click(function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            let row = $(this).parents('tr').first(); // найти контейнер <tr id="row-XXXX"> ....

            if (!confirm('Вы уверены что хотите обновить импортированную страницу?')) return;
            page_import(id, row.find('.folder').val(), true);
        });

        $("tr button.start").click(function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            let row = $(this).parents('tr').first(); // найти контейнер <tr id="row-XXXX"> ....

            row.find('.import-block').hide();
            page_import(id, row.find('.folder').val());
        });

        $("tr button.tohide").click(function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            let row = $(this).parents('tr').first(); // найти контейнер <tr id="row-XXXX"> ....

            page_toggle(id, 'hide')
        });

        $("tr button.toshow").click(function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            let row = $(this).parents('tr').first(); // найти контейнер <tr id="row-XXXX"> ....
            page_toggle(id, 'show')
        });

        $("tr button.cancel").click(function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            let row = $(this).parents('tr').first(); // найти контейнер <tr id="row-XXXX"> ....

            row.find('.import-block').hide();
            show_button(id, 'tohide');
            show_button(id, 'import');
        });

        $("tr button.delete").click(function (e) {
            e.preventDefault();
            let id = $(this).data('id');
            let row = $(this).parents('tr').first(); // найти контейнер <tr id="row-XXXX"> ....

            if (!confirm('Вы уверены что хотите удалить импортированную страницу?')) return;
            page_delete(id);
        });
    }

    /* === end === */

});