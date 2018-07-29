/**
 * Markdown Editor for Hello-Markdown
 * Author: N2ET
 * Author URI: http://www.busyscript.com
 */

(function($) {

    var markdownConfig = helloMarkdownConfig || {
        res: {

        }
     };

    function Preview(options) {
        this.options = $.extend({
            type: 'frame'
        }, options);

        this._init();
    }

    $.extend(Preview.prototype, {
        _init: function() {
            this._initEl();
            this._initEvents();
        },

        _initEvents: function() {
            this.$el.on('preview-style-loaded', this.updateHeight.bind(this));
        },

        _initEl: function() {
            var self = this;
            this.$el = $('<div class="markdown-editor-preview"></div>');
            if(this.isEmbedType()) {
                return;
            }

            this.$frame = $('<iframe class="markdown-editor-preview_frame"></iframe>');
            this.$el.append(this.$frame).appendTo(document.body);

            /**
             * iframe只在插入文档时才有document
             * 移动iframe会导致，文档重新生成
             */
            setTimeout(function() {
                var $head = $(self.$frame.get(0).contentDocument.head);
                var $link = $('<link type="text/css" rel="stylesheet" href="' + markdownConfig.res.css_highlight + '" />');
                $head.append($link);
                $link.on('load', function() {
                    self.$el.trigger('preview-style-loaded');
                });
            }, 0);

        },

        isEmbedType: function () {
            return this.options.type !== 'frame';
        },

        getEl: function () {
            return this.$el;
        },

        update: function(text, notUpdateHeight) {
            var markdown = marked(text);
            var $container = this.$el;
            if(!this.isEmbedType()) {
                $container = $(this.$frame.get(0).contentDocument.body);
            }

            $container.get(0).innerHTML = markdown;
            doHighlight($container);

            if(!notUpdateHeight) {
                this.updateHeight();
            }
        },

        updateHeight: function() {
            if(this.isEmbedType()) {
                return;
            }

            this.$frame.innerHeight(
                this.$frame.get(0).contentDocument.body.scrollHeight
            );
        }
    });


    function MarkdownEditor(options) {
        this.options = $.extend({
            updateDelayTime: 50
        }, options);

        this._init();
    }

    $.extend(MarkdownEditor.prototype, {
        _init: function() {
            this.$editor = this.options.$editor;
            this.preview = this.options.preview;

            this._initEvents();

            this.updatePreview(true);
        },

        _initEvents: function() {
            this.$editor.on('input', this.updatePreview.bind(this));
        },

        updatePreview: function(updateImmediately) {
            if(this.updateID) {
                clearTimeout(this.updateID);
            }

            if(updateImmediately) {
                this._updatePreview();
                return;
            }

            this.updateID = setTimeout(function() {
                this._updatePreview();
                this.updateID = null;
            }.bind(this), this.options.updateDelayTime);
        },

        _updatePreview: function() {
            this.preview.update(
                this.$editor.val()
            );
        }
    });

    var editorConfig = {
        post: function () {
            var $editor = $('#postdivrich #content'),
                preview;

            if(!$editor.size()) {
                return;
            }

            preview = new Preview();

            $('#wp-content-editor-container').after(
                preview.getEl()
            );
            return {
                $editor: $editor,
                preview: preview
            };
        },

        comment: function() {
            var $editor = $('#comment'),
                preview;

            if(!$editor.size()) {
                return;
            }

            preview = new Preview();
            $editor.closest('.comment-form-comment').after(
                preview.getEl()
            );

            return {
                $editor: $editor,
                preview: preview
            };
        }
    };

    function initEditorPreview() {
        ['post', 'comment'].forEach(function(type) {
            var configFn = editorConfig[type],
                config;
            if(!configFn || !(config = configFn())) {
                return;
            }

            new MarkdownEditor(config);
        });
    }

    function initMarkdownView() {
        var $el = $('.markdown-view');
        if(!$el.size()) {
            return;
        }
        $el.each(function() {
            var el = this;
            el.innerHTML = marked(el.innerHTML);
            $(el).show();
        });

    }

    function doHighlight($container) {
        $container = $container || $('body');
        $container.find('pre code').each(function() {
            var el = this;
            hljs.highlightBlock(el);
        });

    }

    $(document).ready(function() {
        initEditorPreview();
        initMarkdownView();
        doHighlight();
    });

}(jQuery));