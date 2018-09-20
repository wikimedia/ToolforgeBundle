require('./jquery.i18n.min.js');
(function () {
    // Load translations with 'en.json' as a fallback
    var messagesToLoad = {};

    /** global: toolforgeI18nLang */
    /** global: toolforgeI18nPath */
    messagesToLoad[toolforgeI18nLang] = toolforgeI18nPath;

    /** global: toolforgeI18nLang */
    if (toolforgeI18nLang !== 'en') {
        messagesToLoad.en = toolforgeI18nEnPath;
    }

    $.i18n({
        locale: toolforgeI18nLang
    }).load(messagesToLoad);
})();
