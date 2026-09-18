// Self-contained Stripe Elements page rendered inside a WebView. It receives the
// PaymentIntent client secret and publishable key, confirms the card payment, and
// posts the result back to React Native via window.ReactNativeWebView.postMessage.
export function stripeHtml(clientSecret, publishableKey) {
  return `<!doctype html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<script src="https://js.stripe.com/v3/"></script>
<style>
  * { box-sizing: border-box; font-family: -apple-system, Roboto, "Segoe UI", sans-serif; }
  body { margin: 0; padding: 20px; background: #f6f6f2; color: #20291f; }
  h1 { font-size: 17px; margin: 0 0 16px; }
  #card { padding: 14px; border: 1px solid #e4e7df; border-radius: 10px; background: #fff; }
  button {
    margin-top: 18px; width: 100%; padding: 15px; border: 0; border-radius: 10px;
    background: #141a12; color: #fff; font-size: 15px; font-weight: 600;
  }
  button:disabled { opacity: .55; }
  #msg { margin-top: 14px; color: #a23b28; font-size: 13px; min-height: 18px; }
  .hint { margin-top: 10px; color: #7c857a; font-size: 12px; }
</style>
</head>
<body>
<h1>Card details</h1>
<div id="card"></div>
<button id="pay">Pay securely</button>
<div id="msg"></div>
<p class="hint">Test card 4242 4242 4242 4242 &middot; any future date &middot; any CVC</p>
<script>
  var post = function (payload) {
    window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify(payload));
  };
  try {
    var stripe = Stripe(${JSON.stringify(publishableKey)});
    var elements = stripe.elements();
    var card = elements.create('card', { style: { base: { fontSize: '16px', color: '#20291f' } } });
    card.mount('#card');
    var button = document.getElementById('pay');
    var msg = document.getElementById('msg');
    button.addEventListener('click', function () {
      button.disabled = true;
      msg.textContent = '';
      stripe.confirmCardPayment(${JSON.stringify(clientSecret)}, { payment_method: { card: card } })
        .then(function (result) {
          if (result.error) {
            msg.textContent = result.error.message;
            button.disabled = false;
            post({ type: 'error', message: result.error.message });
          } else if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
            post({ type: 'success', paymentIntentId: result.paymentIntent.id });
          } else {
            msg.textContent = 'Payment needs another step. Please try again.';
            button.disabled = false;
            post({ type: 'pending', status: result.paymentIntent ? result.paymentIntent.status : 'unknown' });
          }
        })
        .catch(function (e) {
          msg.textContent = String(e);
          button.disabled = false;
          post({ type: 'error', message: String(e) });
        });
    });
    post({ type: 'ready' });
  } catch (e) {
    post({ type: 'error', message: 'Stripe failed to load: ' + String(e) });
  }
</script>
</body>
</html>`;
}
