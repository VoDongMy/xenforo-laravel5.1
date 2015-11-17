/**
 * @author kier
 */
/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
    XenForo.UserTagger = function($element) { this.__construct($element); };
    XenForo.UserTagger.prototype =
    {
        __construct: function($textarea)
        {
            this.$textarea = $textarea;
            this.url = $textarea.data('acurl') || XenForo.AutoComplete.getDefaultUrl();
            this.acResults = new XenForo.AutoCompleteResults({
                onInsert: $.context(this, 'insertAutoComplete')
            });

            var self = this,
                hideCallback = function() {
                    setTimeout(function() {
                        self.acResults.hideResults();
                    }, 200);
                };

            $(document).on('scroll', hideCallback);

            $textarea.on('click blur', hideCallback);
            $textarea.on('keydown', function(e) {
                var prevent = true,
                    acResults = self.acResults;

                if (!acResults.isVisible())
                {
                    return;
                }

                switch (e.keyCode)
                {
                    case 40: acResults.selectResult(1); break; // down
                    case 38: acResults.selectResult(-1); break; // up
                    case 27: acResults.hideResults(); break; // esc
                    case 13: acResults.insertSelectedResult(); break; // enter

                    default:
                        prevent = false;
                }

                if (prevent)
                {
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.preventDefault();
                }
            });
            $textarea.on('keyup', function(e) {
                var autoCompleteText = self.findCurrentAutoCompleteOption();
                if (autoCompleteText)
                {
                    self.triggerAutoComplete(autoCompleteText);
                }
                else
                {
                    self.hideAutoComplete();
                }
            });
        },

        findCurrentAutoCompleteOption: function()
        {
            var $textarea = this.$textarea;

            $textarea.focus();
            var sel = $textarea.getSelection(),
                testText,
                lastAt;

            if (!sel || sel.end <= 1)
            {
                return false;
            }

            testText = $textarea.val().substring(0, sel.end);
            lastAt = testText.lastIndexOf('@');

            if (lastAt != -1 && (lastAt == 0 || testText.substr(lastAt - 1, 1).match(/(\s|[\](,]|--)/)))
            {
                var afterAt = testText.substr(lastAt + 1);
                if (!afterAt.match(/\s/) || afterAt.length <= 10)
                {
                    return afterAt;
                }
            }

            return false;
        },

        insertAutoComplete: function(name)
        {
            var $textarea = this.$textarea;

            $textarea.focus();
            var sel = $textarea.getSelection(),
                testText;

            if (!sel || sel.end <= 1)
            {
                return false;
            }

            testText = $textarea.val().substring(0, sel.end);

            var lastAt = testText.lastIndexOf('@');
            if (lastAt != -1)
            {
                $textarea.setSelection(lastAt, sel.end);
                $textarea.replaceSelectedText('@' + name + ' ', 'collapseToEnd');;
                this.lastAcLookup = name + ' ';
            }
        },

        triggerAutoComplete: function(name)
        {
            if (this.lastAcLookup && this.lastAcLookup == name)
            {
                return;
            }

            this.hideAutoComplete();
            this.lastAcLookup = name;
            if (name.length > 2 && name.substr(0, 1) != '[')
            {
                this.acLoadTimer = setTimeout($.context(this, 'autoCompleteLookup'), 200);
            }
        },

        autoCompleteLookup: function()
        {
            if (this.acXhr)
            {
                this.acXhr.abort();
            }

            this.acXhr = XenForo.ajax(
                this.url,
                { q: this.lastAcLookup },
                $.context(this, 'showAutoCompleteResults'),
                { global: false, error: false }
            );
        },

        showAutoCompleteResults: function(ajaxData)
        {
            this.acXhr = false;
            this.acResults.showResults(
                this.lastAcLookup,
                ajaxData.results,
                this.$textarea
            );
        },

        hideAutoComplete: function()
        {
            this.acResults.hideResults();

            if (this.acLoadTimer)
            {
                clearTimeout(this.acLoadTimer);
                this.acLoadTimer = false;
            }
        }
    };

    // *********************************************************************

    // Register inline moderation forms
    XenForo.register('textarea.UserTagger', 'XenForo.UserTagger');
}
(jQuery, this, document);

