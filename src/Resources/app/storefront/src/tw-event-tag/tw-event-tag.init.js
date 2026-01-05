(async () => {
    const instanceKey = window.tweakwiseConfig?.instanceKey;
    if (!instanceKey) return;

    if (window.__tweakwiseEventTagLoaded) return;
    window.__tweakwiseEventTagLoaded = true;

    if (!window.tweakwise || typeof window.tweakwise.getProfileKey !== 'function') {
        return;
    }

    const profileKey = await window.tweakwise.getProfileKey();
    const scoutUrl = "//navigator-analytics.tweakwise.com/bundles/scout.js";

    (function(w, d, l, i, p, u) {
        w['_twa'] = l;
        w[l] = w[l] || [];
        w[l].push({ 'twa.start': new Date().getTime(), event: 'twa.js' });
        w[l].push({ 'twa.instance': i, event: 'twa.init' });

        p && w[l].push({ 'twa.profile': p, event: 'twa.profile' });
        if(p){ w[l].getProfileKey = function(){ return p; } }

        var f = d.getElementsByTagName('script')[0],
            j = d.createElement('script');
        j.async = true;
        j.src = u;
        f.parentNode.insertBefore(j, f);
    })(window, document, 'tweakwiseLayer', instanceKey, profileKey, scoutUrl);
})();