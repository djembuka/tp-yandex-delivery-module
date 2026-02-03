export function createPointsItem({
    id,
    title,
    type,
    schedule,
    address,
    coords,
    json,
  }) {
    let item = document.createElement('div');
    item.className = 'yd-popup-list__item';
    item.setAttribute('data-id', id);
    item.setAttribute('data-address', address);
    item.setAttribute('data-coords', coords);
    item.setAttribute('data-json', json);

    item.innerHTML = `
      <div class="yd-popup-list__title">${title}</div>
      <div class="yd-popup-list__text">
      <span>${type}</span> ${schedule}<br>
      ${address}
      </div>
      <div class="yd-popup-btn yd-popup-btn--red">${BX.message(
        'TWINPX_JS_SELECT'
      )}</div>
    `;

    return item;
  }