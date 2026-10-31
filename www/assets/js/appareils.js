// QR code du code de connexion d'un appareil (page /compte/appareils), dessiné avec qrcode.js (MIT, Kazuhiko Arase).
document.querySelectorAll('[data-qr]').forEach(function (element) {
    var qr = qrcode(0, 'M');
    qr.addData(element.getAttribute('data-qr'));
    qr.make();
    element.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 4, scalable: true });
});
