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

export const getPvzPopupContainer = (yadeliveryMode) => `<div class="yd-popup-container yd-popup--map ${
      yadeliveryMode === 'simple' ? 'yd-popup--simple' : ''
    }">
        <div class="yd-popup-error-message">
          <div class="yd-popup-error__message">
            <i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>
            ${BX.message('TWINPX_JS_NO_YMAP_KEY')}
          </div>
        </div>
        <div id="ydPopupMap" class="yd-popup-map load-circle"></div>
        <div class="yd-popup-slide">
          <div class="yd-popup-slide-wrapper">
            <div class="yd-popup-slide-detail"></div>
            <div class="yd-popup-slide-error-form">
              <form action="" novalidate="">
                <div class="yd-popup-form">
                  <div class="b-float-label">
                      <input name="PropFio" id="ydSlideFormFio" type="text" value="" required="" data-code="PropFio">
                      <label for="ydSlideFormFio">${BX.message(
                        'TWINPX_JS_FIO'
                      )}</label>
                  </div>

                  <div class="b-float-label">
                      <input name="PropEmail" id="ydSlideFormEmail" type="email" value="" data-code="PropEmail">
                      <label for="ydSlideFormEmail">${BX.message(
                        'TWINPX_JS_EMAIL'
                      )}</label>
                  </div>

                  <div class="b-float-label">
                      <input name="PropPhone" id="ydSlideFormPhone" type="tel" value="" required="" data-code="PropPhone">
                      <label for="ydSlideFormPhone">${BX.message(
                        'TWINPX_JS_PHONE'
                      )}</label>
                  </div>
                  
                  <input name="PropAddress" id="ydSlideFormAddress" type="hidden" value="" data-code="PropAddress">
                </div>

                <div class="yd-popup-form__submit">
                    <button class="yd-popup-form__btn" type="submit">${BX.message(
                      'TWINPX_JS_CONTINUE'
                    )}</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="yd-popup-list">
          <div class="yd-popup-list__back">
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="6.446" height="10.891" viewBox="0 0 6.446 10.891">
              <defs><clipPath id="clip-path"><rect width="6.446" height="10.891" transform="translate(0 0)" fill="none" stroke="#0b0b0b" stroke-width="1"/></clipPath></defs>
              <g transform="translate(0 0)"><g clip-path="url(#clip-path)"><path d="M5.446,9.891,1,5.445,5.446,1" fill="none" stroke="#0b0b0b" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/></g></g>
            </svg>
            ${BX.message('TWINPX_JS_RETURN_LIST')}
          </div>
          <div class="yd-popup-list-wrapper load-circle"></div>
          <div class="yd-popup-list-detail-wrapper">
            <div class="yd-popup-list-detail"></div>
            <div class="yd-popup-error-form">
              <form action="" novalidate="">
                <div class="yd-popup-form">
                  <div class="b-float-label">
                      <input name="PropFio" id="ydFormFio" type="text" value="" required="" data-code="PropFio">
                      <label for="ydFormFio">${BX.message(
                        'TWINPX_JS_FIO'
                      )}</label>
                  </div>

                  <div class="b-float-label">
                      <input name="PropEmail" id="ydFormEmail" type="email" value="" data-code="PropEmail">
                      <label for="ydFormEmail">${BX.message(
                        'TWINPX_JS_EMAIL'
                      )}</label>
                  </div>

                  <div class="b-float-label">
                      <input name="PropPhone" id="ydFormPhone" type="tel" value="" required="" data-code="PropPhone">
                      <label for="ydFormPhone">${BX.message(
                        'TWINPX_JS_PHONE'
                      )}</label>
                  </div>
                  
                  <input name="PropAddress" id="ydFormAddress" type="hidden" value="" data-code="PropAddress">
                </div>

                <div class="yd-popup-form__submit">
                    <span class="yd-popup-form__btn yd-popup-form__btn--skip">${BX.message(
                      'TWINPX_JS_RESET'
                    )}</span>
                    <button class="yd-popup-form__btn" type="submit">${BX.message(
                      'TWINPX_JS_CONTINUE'
                    )}</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="yd-popup-mobile-top">
          <div class="yd-popup-btn yd-popup-btn--light yd-popup-btn--active">${BX.message(
            'TWINPX_JS_ONCART'
          )}</div>
          <div class="yd-popup-btn yd-popup-btn--light">${BX.message(
            'TWINPX_JS_ONLIST'
          )}</div>
        </div>
        <div class="yd-popup-mobile-bottom">
          <div class="yd-popup-btn yd-popup-btn--gray">${BX.message(
            'TWINPX_JS_CLOSE'
          )}</div>
        </div>
      </div>`;