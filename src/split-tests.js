export default function split_tests_init() {

    if (typeof split_tests == 'undefined') {
        return;
    }

    function findHref(el) {
        if (el.getAttribute('href')) {
            return el.getAttribute('href');
        } else if (el.nodeName != 'BODY') {
            return findHref(el.parentNode);
        } else {
            return null;
        }
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
        let redirect = split_tests.onload.filter(event => event[0] == 'redirect');
        if (redirect.length > 0) {
            // This is here to redirect unpublished title tests back to the
            // default permalink.
            window.location = redirect[0][1];
            return;
        } else {
            postEvents(split_tests.onload);
        }
    }

    if (split_tests.tests) {
        for (let test of split_tests.tests) {
            postEvents([["test", parseInt(test.id), test.variant]]);

            if (test.content) {
                for (let replacement of test.content) {
                    let targets = document.querySelectorAll(replacement.selector);
                    for (let target of targets) {
                        if (replacement.search && target.innerText.trim() != replacement.search.trim()) {
                            continue;
                        }
                        target.innerText = replacement.replace;
                    }
                }
            }

            if (test.conversion == 'click') {
                const selector = test.click_selector;
                const content = test.click_content;
                const targets = document.querySelectorAll(selector);
                for (let target of targets) {
                    if (content && target.innerText.trim() != content.trim()) {
                        continue;
                    }
                    target.addEventListener('click', async e => {
                        e.preventDefault();
                        await postEvents([["convert", parseInt(id), test.variant]]);
                        let href = findHref(e.target);
                        if (href) {
                            window.location = href;
                        } else {
                            console.log('Error: No href attribute found.');
                        }
                    });
                }
            }
        }
    }
}

window.addEventListener('load', split_tests_init);