window.addEventListener('load', () => {
    if (! split_tests) {
        return;
    }
    fetch(`/wp-json/split-tests/v1/events?_wpnonce=${split_tests.nonce}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(split_tests.events)
    });
});