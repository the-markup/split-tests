export default function split_tests_init() {

    if (typeof split_tests == 'undefined') {
        return;
    }

    function postEvents(events) {
        if (!events || events.length < 1) {
            return;
        }
        return fetch(`${split_tests.endpoint_url}?_wpnonce=${split_tests.nonce}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(events)
        });
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