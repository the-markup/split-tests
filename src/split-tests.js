window.addEventListener('load', () => {
    if (! split_tests_events) {
        return;
    }
    fetch('/wp-json/split-tests/v1/events', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(split_tests_events)
    });
});