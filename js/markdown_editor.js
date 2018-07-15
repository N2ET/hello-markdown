/**
 * Created by GNPLX on 2018/7/14.
 */

(function($) {

    function MarkdownEditor(options) {
        this.options = $.extend({
            updateDelayTime: 50
        }, options);

        this._init();
    }

    $.extend(MarkdownEditor.prototype, {
        _init: function() {
            this.$editor = this.options.$editor;
            this.$preview = this.options.$preview;

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
            this.$preview.get(0).innerHTML = marked(
                this.$editor.val()
            );
        }
    });

    function getPreviewEl() {
        return $('<div class="markdown-editor-preview"></div>');
    }

    var editorConfig = {
        post: function () {
            var $editor = $('#content'),
                $preview;

            if(!$editor.size()) {
                return;
            }

            $preview = getPreviewEl();
            $('#wp-content-editor-container').after($preview);
            return {
                $editor: $editor,
                $preview: $preview
            };
        },

        comment: function() {
            var $editor = $('#comment'),
                $preview;

            if(!$editor.size()) {
                return;
            }

            $preview = getPreviewEl();
            $editor.closest('.comment-form-comment').after($preview);

            return {
                $editor: $editor,
                $preview: $preview
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
        $container = $container || $('pre code');

        $container.each(function() {
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