// Offline Support and Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        const swPath = (typeof APP_URL !== 'undefined' ? APP_URL : '') + '/sw.js';
        navigator.serviceWorker.register(swPath)
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed');
            });
    });
}

// Listen for online/offline events
window.addEventListener('online', function() {
    console.log('Connection restored');
    if (typeof syncOfflineTransactions === 'function') {
        syncOfflineTransactions();
    }
});

window.addEventListener('offline', function() {
    console.log('Connection lost - entering offline mode');
});
