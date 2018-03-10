<section>
  <p>{l s='Оплатите заказ ниже' d='Modules.Yadpay.Shop'}
    <dl>
      <dt>{l s='Сумма' d='Modules.Yadpay.Shop'}</dt>
      <dd>{$checkTotal}</dd>
      <dt>{l s='Порядок заказа' d='Modules.Yadpay.Shop'}</dt>
      <dd>{$checkDescription}</dd>
    </dl>
  </p>
</section>
<script type="text/javascript">
  function goToPayTo(url) {
    alert(url);
  }
</script>