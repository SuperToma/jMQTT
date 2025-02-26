/*
 * Table of Contents Generator library for jQuery
 *
 * Inspired from https://github.com/idiotWu/jQuery-TOC
 *
 * Copyright (c) 2019 domotruc
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 */
(function(factory) {
    if (typeof define === 'function' && define.amd) {
        // AMD. Register as an anonymous module.
        define([ 'jquery' ], factory);
    } else {
        // Browser globals
        factory(jQuery);
    }
}(function($) {
    'use strict';

    /**
     * get header level
     *
     * @param {String} header: header's tag name
     * @return {Number}
     */
    var getLevel = function(header) {
        if (typeof header !== 'string') {
            return 0;
        }

        var decs = header.match(/\d/g);
        return decs ? Math.min.apply(null, decs) : 1;
    };

    /**
     * create ordered list
     *
     * @param {jQuert} $wrapper
     * @param {Number} count
     * @return {jQuery} list
     */
    var createList = function($wrapper, count) {
        while (count--) {
            $wrapper = $('<ul/>').appendTo($wrapper);

            if (count) {
                $wrapper = $('<li/>').appendTo($wrapper);
            }
        }

        return $wrapper;
    };

    /**
     * insert position jump back
     *
     * @param {jQuery} $currentWrapper: current insert point
     * @param {Number} offset: distance between current's and target's depth
     * @return {jQuery} insert point
     */
    var jumpBack = function($currentWrapper, offset) {
        while (offset--) {
            $currentWrapper = $currentWrapper.parent();
        }

        return $currentWrapper;
    };

    /**
     * set element href/id and content
     *
     * @param {Boolean} overwrite: whether overwrite source element existed id
     * @param {String} prefix: prefix to prepend to href/id
     * @return {Function}
     */
    var setAttrs = function(overwrite, prefix) {
        return function($src, $target, index, number) {
            var content = $src.text();
            var id_pre = prefix + '-' + index;
            var number_pre = number + '. ';

            // To avoid repetition of the number in case several toc are put in the page
            if (content.substr(0, number_pre.length) == number_pre)
                content = content.substr(number_pre.length);

            $target.append(
                    $('<span/>', {'class' : 'tocnumber', 'text' : number + ' '}),
                    $('<span/>', {'class' : 'toctext'}).html('<span class="tocfirst-letter">' + content.substr(0,1) + '</span>' + content.substr(1)));

            var id = overwrite ? id_pre : ($src.attr('id') || id_pre);

            $src.attr('id', id).text(number_pre + content);
            $target.attr('href', '#' + id);
        };
    };

    /**
     * build table of contents
     *
     * @param {Object} options
     * @return {jQuery} list
     */
    var buildTOC = function(options) {
        var selector = options.selector;
        var scope = options.scope;

        var $ret = $('<ul/>');
        var $wrapper = $ret;
        var $lastLi = null;

        var prevDepth = getLevel(selector);
        var indices = [0];
        var _setAttrs = setAttrs(options.overwrite, options.prefix);

        $(scope).find(selector).each(
                function(index, elem) {
                    var currentDepth = getLevel(elem.tagName);
                    var offset = currentDepth - prevDepth;

                    if (offset > 0) {
                        $wrapper = createList($lastLi, offset);
                        var n=indices.length;
                        for (var i=n ; i<n+offset ; i++) {
                            indices[i] = 1;
                        }
                        indices[indices.length-1] = 0;
                    }
                    else if (offset < 0) {
                        // should be once more level to jump back
                        // eg: h2 + h3 + h2, offset = h2 - h3 = -1
                        //
                        // ol <------+ target
                        // li |
                        // ol ---+ current
                        // li
                        //
                        // jumpback = target - current = 2
                        $wrapper = jumpBack($wrapper, -offset * 2);
                        indices = indices.slice(0, indices.length+offset);
                    }

                    indices[indices.length-1]++;

                    if (!$wrapper.length) {
                        $wrapper = $ret;
                    }

                    var $li = $('<li/>');
                    var $a = $('<a/>');

                    _setAttrs($(elem), $a, index, indices.join('.'));

                    $li.append($a).appendTo($wrapper);
                    $li.addClass("toclevel-" + currentDepth).addClass("tocsection-" + (index + 1));

                    $lastLi = $li;
                    prevDepth = currentDepth;
                });

        return $ret;
    };

    /**
     * init table of contents
     *
     * @param {Object}
     *            [option]: TOC options, available props: {String} [selector]:
     *            headers selector, default is 'h1, h2, h3, h4, h5, h6' {String}
     *            [scope]: selector to specify elements search scope, default is
     *            'body' {Boolean} [overwrite]: whether to overwrite existed
     *            headers' id, default is false {String} [prefix]: string to
     *            prepend to id/href prop, default is 'toc' {String} [toctitle]:
     *            TOC title displayed first, default is empty
     * @return {jQuery} $this
     */
    $.fn.initTOC = function(options) {
        var defaultOpts = {
            selector : 'h1, h2, h3, h4, h5, h6',
            scope : 'body',
            overwrite : false,
            prefix : 'toc',
            toctitle : ''
        };

        options = $.extend(defaultOpts, options);

        var selector = options.selector;

        if (typeof selector !== 'string') {
            throw new TypeError('selector must be a string');
        }

        if (!selector.match(/^(?:h[1-6],?\s*)+$/g)) {
            throw new TypeError('selector must contains only h1-6');
        }

        $('<div/>', {'class' : 'toctitle'}).appendTo($(this)).html(options.toctitle);
        $('<div/>', {'id' : 'toc', 'class' : 'toc'}).appendTo($(this)).append(buildTOC(options));

        var currentHash = location.hash;

        if (currentHash) {
            setTimeout(function() {
                var anchor = document.getElementById(currentHash.slice(1));
                if (anchor)
                    anchor.scrollIntoView();
            }, 0);
        }

        return $(this);
    };
}));
