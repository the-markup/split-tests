export default function split_tests_init() {

    if (typeof split_tests == 'undefined') {
        return;
    }

    function postEvents(events) {
        if (!events || events.length < 1) {
            return;
        }
        let promises = [];
        for (let e of events) {
            let response = fetch(split_tests.endpoint_url, {
                method: 'POST',
                headers:{
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    t: e[0],             // type (test or convert)
                    i: e[1],             // post ID
                    v: e[2],             // variant
                    n: split_tests.nonce // nonce
                })
            });
            promises.push(response);
        }
        return Promise.all(promises);
    }

    if (split_tests.onload) {
        postEvents(split_tests.onload);
    }

    if (split_tests.dom) {
        for (let id in split_tests.dom) {
            const variant = split_tests.dom[id];
            postEvents([["test", parseInt(id), variant.index]]);

            if (variant.content) {
                for (let replacement of variant.content) {
                    let targets = document.querySelectorAll(replacement.selector);
                    for (let target of targets) {
                        if (replacement.search && target.innerText.trim() != replacement.search.trim()) {
                            continue;
                        }
                        target.innerText = replacement.replace;
                    }
                }
            }

            if (variant.conversion == 'click') {
                const selector = variant.click_selector;
                const content = variant.click_content;
                const targets = document.querySelectorAll(selector);
                for (let target of targets) {
                    if (content && target.innerText.trim() != content.trim()) {
                        continue;
                    }
                    target.addEventListener('click', async e => {
                        e.preventDefault();
                        await postEvents([["convert", parseInt(id), variant.index]]);
                        window.location = e.target.getAttribute('href');
                    });
                }
            }
        }
    }
}

window.addEventListener('load', split_tests_init);