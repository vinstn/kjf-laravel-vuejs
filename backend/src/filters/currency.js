export default function currencyUSD(value) {
  return new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'})
    .format(value);
}
