/* Based on https://github.com/Minishlink/web-push-php-example/ */
self.addEventListener('push', function (event) {
  if (!(self.Notification && self.Notification.permission === 'granted')) {
    return;
  }

  const sendNotification= body => {
    const title = "Scat POS";

    return self.registration.showNotification(title, {
      body,
      // Safari doesn't actually use this yet, but be ready
      icon: '/static/icon.iconset/icon_128x128.png',
    });
  };

  if (event.data) {
    const message= event.data.text();
    event.waitUntil(sendNotification(message));
  }
});
