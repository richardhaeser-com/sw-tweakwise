(() => {
    const NS = (window.tweakwise = window.tweakwise || {});

    NS.getProfileKey = function () {
        if (NS.profileKey) return Promise.resolve(NS.profileKey);

        if (!NS.profileKeyPromise) {
            NS.profileKeyPromise = fetch('/tweakwise/profile-key', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
                .then(r => {
                    if (!r.ok) throw new Error(`Failed to load profile key (${r.status})`);
                    return r.json();
                })
                .then(json => {
                    NS.profileKey = json.profileKey;
                    return NS.profileKey;
                });
        }

        return NS.profileKeyPromise;
    };
})();