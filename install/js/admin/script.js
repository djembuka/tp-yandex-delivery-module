window.twinpxYadeliveryFetchURL =
  window.twinpxYadeliveryFetchURL ||
  '/bitrix/tools/twinpx.yadelivery/admin/ajax.php';

//window.twinpxYadeliveryYmapsAPI = false;
window.twinpxYadeliveryYmapsAPI =
  window.twinpxYadeliveryYmapsAPI ||
  window.twinpxYadeliveryYmapsAPI === undefined
    ? true
    : false;

window.newDeliveryPopupOnload = function () {
  const ydContent = document.querySelector('#newDelivery .yd-popup-content'),
    ydForm = document.querySelector('#newDelivery .yd-popup-form'),
    ydBody = ydContent.querySelector('.yd-popup-body'),
    ydOffers = ydBody.querySelector('.yd-popup-offers'),
    ydError = ydContent.querySelector('.yd-popup-error'),
    regExp = {
      email: /^([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i,
      //tel: /^[\+][0-9]?[-\s\.]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/im,//+9 (999) 999 9999
    };

  //fill button
  twinpxYadeliveryFillbutton(ydForm);

  //paysystem select
  twinpxYadeliveryPaysystemSelect(ydForm);

  //tabs
  twinpxYadeliveryTabs('newDeliveryContent');

  function twinpxYadeliveryTabs(winId) {
    let tabs = document.querySelector(`#${winId} .yd-popup-tabs`);
    let navItems = tabs.querySelectorAll('.yd-popup-tabs__nav__item');
    let tabItems = tabs.querySelectorAll('.yd-popup-tabs__tabs__item');

    navItems.forEach((navItem) => {
      navItem.addEventListener('click', (e) => {
        e.preventDefault();
        //class
        navItems.forEach((n) => {
          n.classList.remove('yd-popup-tabs__nav__item--active');
        });
        navItem.classList.add('yd-popup-tabs__nav__item--active');
        //tab
        tabItems.forEach((t) => {
          t.classList.remove('yd-popup-tabs__tabs__item--active');
        });
        tabs
          .querySelector(
            `.yd-popup-tabs__tabs__item[data-tab=${navItem.getAttribute(
              'data-tab'
            )}]`
          )
          .classList.add('yd-popup-tabs__tabs__item--active');
      });
    });
  }

  async function sendOffer(jsonStr) {
    let formData, response;

    formData = new FormData();
    formData.set('action', 'setOfferPrice');
    formData.set('fields', jsonStr);

    response = await fetch(window.twinpxYadeliveryFetchURL, {
      method: 'POST',
      body: formData,
    });

    return response.json();
  }

  function elemLoader(elem, flag) {
    flag
      ? elem.classList.add('load-circle')
      : elem.classList.remove('load-circle');
  }

  elemLoader(ydContent, false);

  //click offer
  ydOffers.addEventListener('click', (e) => {
    if (
      e.target.classList.contains('yd-popup-offers__item') ||
      e.target.closest('.yd-popup-offers__item')
    ) {
      let item = e.target.classList.contains('yd-popup-offers__item')
        ? e.target
        : e.target.closest('.yd-popup-offers__item');
      let data = item.getAttribute('data-json');

      (async () => {
        let formData = new FormData(),
          response,
          result;

        formData.append('action', 'setDelivery');
        formData.append('data', data);

        //preloader
        ydOffers.classList.remove('yd-popup-offers--animate');
        elemLoader(ydOffers, true);
        ydOffers.innerHTML = '';

        let controller = new AbortController();

        setTimeout(() => {
          if (!response) {
            controller.abort();
          }
        }, 20000);

        response = await fetch(window.twinpxYadeliveryFetchURL, {
          method: 'POST',
          body: formData,
        });
        result = await response.json();

        if (result.STATUS !== 'Y') {
          window.ydConfirmer.destroy();
          if (result.RELOAD) {
            window.location.href = result.RELOAD;
          } else {
            window.location.reload();
          }
        } else {
          //error
          //ydOffers.innerHTML = result.ERROR;
          ydError.innerHTML = result.ERROR;
          ydBody.classList.remove('yd-popup-body--result');
          ydOffers.classList.remove('yd-popup-offers--animate');
          elemLoader(ydOffers, true);
          ydOffers.innerHTML = '';
        }
      })();
    }
  });

  //float label input
  ydContent
    .querySelectorAll('.b-float-label input, .b-float-label textarea')
    .forEach((control) => {
      let item = control.closest('.b-float-label'),
        label = item.querySelector('label');

      if (control.value.trim() !== '') {
        label.classList.add('active');
      }

      control.addEventListener('blur', () => {
        if (control.value.trim() !== '') {
          label.classList.add('active');
        } else {
          label.classList.remove('active');
        }
      });

      control.addEventListener('keyup', () => {
        if (item.classList.contains('invalid')) {
          validate(item, control);
        }
      });
    });

  function validate(item, control) {
    //required
    if (
      control.getAttribute('required') === '' &&
      control.value.trim() !== ''
    ) {
      item.classList.remove('invalid');
    }

    Object.keys(regExp).forEach((key) => {
      if (control.getAttribute('type') === key) {
        if (
          (control.value.trim() !== '' && regExp[key].test(control.value)) ||
          (control.getAttribute('required') !== '' &&
            control.value.trim() === '')
        ) {
          item.classList.remove('invalid');
        }
      }
    });
  }

  //input mask
  if (window.BX && window.BX.MaskedInput) {
    new BX.MaskedInput({
      mask: '+7 (999) 999 9999',
      input: BX('ydFormPhone'),
      placeholder: '_',
    });
  }

  //check form
  if (!ydContent.querySelector('form')) return;

  ydContent
    .querySelector('form')
    .querySelectorAll('textarea')
    .forEach(function (textarea) {
      textarea.addEventListener('input', function () {
        this.style.height = this.scrollHeight + 'px';
      });
    });

  ydContent.querySelector('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    let focusElement;
	
	//change active tab (for the sake of height of the modal)
	form.closest('.yd-popup-tabs').querySelector('.yd-popup-tabs__nav__item:first-child').click();
	window.ydConfirmer.adjustPosition();

    //clear error
    ydError.innerHTML = '';

    //required
    form.querySelectorAll('[required]').forEach((reqInput) => {
      if (reqInput.value.trim() === '') {
        if (!focusElement) {
          focusElement = reqInput;
        }
        reqInput.closest('.b-float-label').classList.add('invalid');
      } else {
        reqInput.closest('.b-float-label').classList.remove('invalid');
      }
    });

    Object.keys(regExp).forEach((key) => {
      form.querySelectorAll(`[type=${key}]`).forEach((input) => {
        //required
        if (
          input.getAttribute('required') === '' ||
          input.value.trim() !== ''
        ) {
          if (!regExp[key].test(input.value)) {
            if (!focusElement) {
              focusElement = input;
            }
            input.closest('.b-float-label').classList.add('invalid');
          } else {
            input.closest('.b-float-label').classList.remove('invalid');
          }
        }
      });
    });

    //check offer
    if (document.getElementById('ydFormOrder')) {
      let orderInput = document.getElementById('ydFormOrder'),
        orderId = orderInput.value,
        formData = new FormData(),
        response,
        result;

      formData.append('action', 'checkOrder');
      formData.append('orderId', orderId);

      //preloader
      elemLoader(ydForm, true);

      response = await fetch(window.twinpxYadeliveryFetchURL, {
        method: 'POST',
        body: formData,
      });
      result = await response.json();

      elemLoader(ydForm, false);

      if (result.STATUS !== 'Y') {
        if (!focusElement) {
          focusElement = orderInput;
        }
        orderInput.closest('.b-float-label').classList.add('invalid');
      } else {
        orderInput.closest('.b-float-label').classList.remove('invalid');
      }
    }

    function offersError(message) {
      ydError.innerHTML = `<div class="yd-popup-error__message"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>${message}</div>`;
      ydBody.classList.remove('yd-popup-body--result');
      ydOffers.classList.remove('yd-popup-offers--animate');
      elemLoader(ydOffers, true);
      ydOffers.innerHTML = '';
    }

    //focus
    if (focusElement) {
      focusElement.focus();
    } else {
      //send
      ydBody.classList.add('yd-popup-body--result');

      //fetch request
      let formData = new FormData();
      formData.set('action', 'newGetOffer');
      formData.set('form', $(form).serialize());

      let controller = new AbortController();
      let response;

      setTimeout(() => {
        if (!response) {
          controller.abort();
        }
      }, 20000);

      try {
        response = await fetch(window.twinpxYadeliveryFetchURL, {
          method: 'POST',
          body: formData,
          signal: controller.signal,
        });

        let result = await response.json();

        if (result && typeof result === 'object') {
          if (result.STATUS === 'Y') {
            if (result.ERRORS) {
              offersError(result.ERRORS);
            } else if (result.OFFERS) {
              let html = '<div class="yd-popup-offers__wrapper">';

              result.OFFERS.forEach(({ json, date, time, price }) => {
                html += `<div class="yd-popup-offers__item" data-json='${json}'>
                <div class="yd-popup-offers__info">
                    <span class="yd-popup-offers__date"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/calendar.svg)"></i>${date}</span>
                    <span class="yd-popup-offers__time"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/clock.svg)"></i>${time}</span>
                  </div>
                <b class="yd-popup-offers__price">${price}</b>
                <a href="#" class="twpx-ui-btn">${BX.message(
                  'TWINPX_JS_SELECT'
                )}</a>
              </div>`;
              });

              html += '</div>';

              //remove preloader
              elemLoader(ydOffers, false);

              //html
              ydOffers.innerHTML = html;

              setTimeout(() => {
                ydOffers.classList.add('yd-popup-offers--animate');
              }, 0);
            } else {
              offersError(BX.message('TWINPX_JS_EMPTY_OFFER'));
            }
          } else {
            offersError(BX.message('TWINPX_JS_ERROR'));
          }
        }

        //ydOffers.innerHTML = result;
        setTimeout(() => {
          ydOffers.classList.add('yd-popup-offers--animate');
        }, 100);
      } catch (err) {
        offersError(BX.message('TWINPX_JS_NO_RESPONSE'));
      }
    }
	
  });

	if (ydForm.closest('.yd-popup-content').querySelector('.yd-popup-tabs__tabs__item[data-tab="package"]')) {
		packageForm();
	}
};

window.newDeliveryPvzPopupOnload = function (orderId, pvzId, chosenAddress) {
  const ydContent = document.querySelector('#newDeliveryPvz .yd-popup-content'),
    ydForm = document.querySelector('#newDeliveryPvz .yd-popup-form'),
    ydBody = ydContent.querySelector('.yd-popup-body'),
    ydContainer = ydBody.querySelector('.yd-popup-map-container'),
    ydError = ydContent.querySelector('.yd-popup-error'),
    regExp = {
      email: /^([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i,
      //tel: /^[\+][0-9]?[-\s\.]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/im,//+9 (999) 999 9999
    };

  let payment;

  function elemLoader(elem, flag) {
    flag
      ? elem.classList.add('load-circle')
      : elem.classList.remove('load-circle');
  }

  elemLoader(ydContent, false);

  //fill button
  twinpxYadeliveryFillbutton(ydForm);

  //paysystem select
  twinpxYadeliveryPaysystemSelect(ydForm);

  //tabs
  twinpxYadeliveryTabs('newDeliveryPvz');

  function twinpxYadeliveryTabs(winId) {
    let tabs = document.querySelector(`#${winId} .yd-popup-tabs`);
    let navItems = tabs.querySelectorAll('.yd-popup-tabs__nav__item');
    let tabItems = tabs.querySelectorAll('.yd-popup-tabs__tabs__item');

    navItems.forEach((navItem) => {
      navItem.addEventListener('click', (e) => {
        e.preventDefault();
        //class
        navItems.forEach((n) => {
          n.classList.remove('yd-popup-tabs__nav__item--active');
        });
        navItem.classList.add('yd-popup-tabs__nav__item--active');
        //tab
        tabItems.forEach((t) => {
          t.classList.remove('yd-popup-tabs__tabs__item--active');
        });
        tabs
          .querySelector(
            `.yd-popup-tabs__tabs__item[data-tab=${navItem.getAttribute(
              'data-tab'
            )}]`
          )
          .classList.add('yd-popup-tabs__tabs__item--active');
      });
    });
  }

  //float label input
  ydContent
    .querySelectorAll('.b-float-label input, .b-float-label textarea')
    .forEach((control) => {
      let item = control.closest('.b-float-label'),
        label = item.querySelector('label');

      if (control.value.trim() !== '') {
        label.classList.add('active');
      }

      control.addEventListener('blur', () => {
        if (control.value.trim() !== '') {
          label.classList.add('active');
        } else {
          label.classList.remove('active');
        }
      });

      control.addEventListener('keyup', () => {
        if (item.classList.contains('invalid')) {
          validate(item, control);
        }
      });
    });

  function validate(item, control) {
    //required
    if (
      control.getAttribute('required') === '' &&
      control.value.trim() !== ''
    ) {
      item.classList.remove('invalid');
    }

    Object.keys(regExp).forEach((key) => {
      if (control.getAttribute('type') === key) {
        if (
          (control.value.trim() !== '' && regExp[key].test(control.value)) ||
          (control.getAttribute('required') !== '' &&
            control.value.trim() === '')
        ) {
          item.classList.remove('invalid');
        }
      }
    });
  }

  ydContent
    .querySelector('form')
    .querySelectorAll('textarea')
    .forEach(function (textarea) {
      textarea.addEventListener('input', function () {
        this.style.height = this.scrollHeight + 'px';
      });
    });

  ydContent.querySelector('form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    let focusElement;
	
	//change active tab (for the sake of height of the modal)
	form.closest('.yd-popup-tabs').querySelector('.yd-popup-tabs__nav__item:first-child').click();
	window.ydConfirmerPvz.adjustPosition();

    //clear error
    ydError.innerHTML = '';

    //required
    form.querySelectorAll('[required]').forEach((reqInput) => {
      if (reqInput.value.trim() === '') {
        if (!focusElement) {
          focusElement = reqInput;
        }
        reqInput.closest('.b-float-label').classList.add('invalid');
      } else {
        reqInput.closest('.b-float-label').classList.remove('invalid');
      }
    });

    Object.keys(regExp).forEach((key) => {
      form.querySelectorAll(`[type=${key}]`).forEach((input) => {
        //required
        if (
          input.getAttribute('required') === '' ||
          input.value.trim() !== ''
        ) {
          if (!regExp[key].test(input.value)) {
            if (!focusElement) {
              focusElement = input;
            }
            input.closest('.b-float-label').classList.add('invalid');
          } else {
            input.closest('.b-float-label').classList.remove('invalid');
          }
        }
      });
    });

    //check offer
    if (document.getElementById('ydFormPvzOrder')) {
      let orderInput = document.getElementById('ydFormPvzOrder'),
        orderId = orderInput.value,
        formData = new FormData(),
        response,
        result;

      formData.append('action', 'checkOrder');
      formData.append('orderId', orderId);

      //preloader
      elemLoader(ydForm, true);

      response = await fetch(window.twinpxYadeliveryFetchURL, {
        method: 'POST',
        body: formData,
      });
      result = await response.json();

      elemLoader(ydForm, false);

      if (result.STATUS !== 'Y') {
        if (!focusElement) {
          focusElement = orderInput;
        }
        orderInput.closest('.b-float-label').classList.add('invalid');
      } else {
        orderInput.closest('.b-float-label').classList.remove('invalid');
      }
    }

    //focus
    if (focusElement) {
      focusElement.focus();
    } else {
      payment = form.querySelector('[name="PAY_TYPE"]').value;

      //send
      ydBody.classList.add('yd-popup-body--result');

      //create form serialized inputs
      let fields = '';
      for (let i = 0; i < form.elements.length; i++) {
        if (fields) {
          fields += '&';
        }
        fields += `${form.elements[i].getAttribute('name')}=${
          form.elements[i].value
        }`;
      }

      //show map
      let ydPopupContainer,
        ydPopupList,
        ydPopupWrapper,
        ydPopupDetail,
        map,
        objectManager,
        bounds,
        firstGeoObjectCoords,
        regionName,
        pvzPopup,
        centerCoords,
        pointsArray,
        pointsNodesArray = {},
        newBounds = [],
        container = `<div class="yd-popup-container yd-popup--map ${
          pvzId ? 'yd-popup--simple' : ''
        }">
          <div id="ydPopupMap" class="yd-popup-map load-circle"></div>
          <div class="yd-popup-list">
            <div class="yd-popup-list__back">${BX.message(
              'TWINPX_JS_RETURN'
            )}</div>
            <div class="yd-popup-list__chosen">${BX.message(
              'TWINPX_JS_CHOSEN'
            )}</div>
            <div class="yd-popup-list-wrapper load-circle"></div>
            <div class="yd-popup-list-detail"></div>
          </div>
        </div>`;

      let cityInput = document.getElementById('ydFormPvzCity');
      regionName = cityInput ? cityInput.value : '';

      ydContainer.innerHTML = container;
      onPopupShow(pvzId);

      function pointsError(error) {
        ydPopupWrapper.innerHTML = `<div class="yd-popup-error__message"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>${error}</div>`;
        elemLoader(ydPopupDetail, false);
      }

      function offersError(error) {
        ydPopupDetail.innerHTML = `<div class="yd-popup-error__message"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>${error}</div>`;
        elemLoader(ydPopupDetail, false);
      }

      function onObjectEvent(e) {
        let id = e.get('objectId');

        let pointObject = pointsArray.find((p) => {
          return p.id === id;
        });

        clickPlacemark(id, pointObject.address, map, pointObject.coords);
      }

      function onClusterEvent() {
        //active button
        topBtns[0].classList.remove('yd-popup-btn--active');
        topBtns[1].classList.add('yd-popup-btn--active');
        //map-list mode
        ydPopupContainer.classList.remove('yd-popup--map');
        ydPopupContainer.classList.remove('yd-popup--detail');
        ydPopupContainer.classList.add('yd-popup--list');
      }

      async function clickPlacemark(id, address, map, coords) {
        map.panTo(coords).then(() => {
          map.setZoom(16);
        });

        //set detail mode
        ydPopupContainer.classList.add('yd-popup--detail');
        ydPopupContainer.classList.remove('yd-popup--map');
        ydPopupContainer.classList.remove('yd-popup--list');

        //add preloader
        elemLoader(ydPopupDetail, true);
        ydPopupDetail.innerHTML = '';

        //get offers
        let formData = new FormData();
        formData.set('action', 'pvzOfferAdmin');
        formData.set('fields', `${fields}&id=${id}&address=${address}`);

        let controller = new AbortController();
        let response;

        setTimeout(() => {
          if (!response) {
            controller.abort();
          }
        }, 20000);

        try {
          response = await fetch(window.twinpxYadeliveryFetchURL, {
            method: 'POST',
            body: formData,
            signal: controller.signal,
          });

          let result = await response.json();

          if (result && typeof result === 'object') {
            if (result.STATUS === 'Y') {
              if (result.ERRORS) {
                offersError(result.ERRORS);
              } else {
                if (result.OFFERS) {
                  let html = '';

                  html = `<div class="yd-h3">${BX.message(
                    'TWINPX_JS_SELECT'
                  )}</div>`;

                  result.OFFERS.forEach(({ json, date, time, price }) => {
                    html += `
                      <div class="yd-popup-offer" data-json='${json}'>
                        <div class="yd-popup-offer__info">
                          <div class="yd-popup-offer__date">${date}</div>
                          <div class="yd-popup-offer__time">${time}</div>
                        </div>
                        <div class="yd-popup-offer__price">${price}</div>
                        <div class="twpx-ui-btn">${BX.message(
                          'TWINPX_JS_SELECT'
                        )}</div>
                      </div>
                    `;
                  });

                  //remove preloader
                  elemLoader(ydPopupDetail, false);
                  //html
                  ydPopupDetail.innerHTML = html;
                } else {
                  offersError(BX.message('TWINPX_JS_EMPTY_OFFER'));
                }
              }
            } else {
              offersError(BX.message('TWINPX_JS_EMPTY_OFFER'));
            }
          }

          //item content
          let item = ydPopupList
            .querySelector(`[data-id="${id}"]`)
            .cloneNode(true);
          ydPopupDetail.prepend(item);
          ydPopupDetail.scrollTo({
            top: 0,
          });
        } catch (err) {
          offersError(BX.message('TWINPX_JS_ERROR'));
        }
      }

      function onPopupShow(pvzId) {
        ydPopupContainer = document.querySelector(
          '#newDeliveryPvz .yd-popup-container'
        );
        ydPopupList = ydPopupContainer.querySelector('.yd-popup-list');
        ydPopupWrapper = ydPopupList.querySelector('.yd-popup-list-wrapper');
        ydPopupDetail = ydPopupList.querySelector('.yd-popup-list-detail');

        pointsArray = [];

        //choose offer event
        ydPopupDetail.addEventListener('click', async (e) => {
          e.preventDefault();
          if (e.target.classList.contains('twpx-ui-btn')) {
            let btn = e.target;
            let offerElem = btn.closest('.yd-popup-offer');
            let jsonStr = offerElem.getAttribute('data-json'); //string

            let result = await sendOffer(jsonStr);

            if (result && result.STATUS === 'Y') {
              if (result.RELOAD) {
                document.location.href = result.RELOAD;
              } else {
                document.location.reload(); //��������� ��������
              }
            }
          }
        });

        //ymaps
        if (window.ymaps && window.ymaps.ready) {
          ymaps.ready(() => {
            //geo code
            const myGeocoder = ymaps.geocode(regionName, {
              results: 1,
            });

            myGeocoder.then((res) => {
              // first result, its coords and bounds
              let firstGeoObject = res.geoObjects.get(0);
              firstGeoObjectCoords = firstGeoObject.geometry.getCoordinates();
              bounds = firstGeoObject.properties.get('boundedBy');
              newBounds = bounds;

              map = new ymaps.Map(
                'ydPopupMap',
                {
                  center: firstGeoObjectCoords,
                  zoom: 9,
                  controls: ['searchControl', 'zoomControl'],
                },
                {
                  suppressMapOpenBlock: true,
                }
              );

              let customBalloonContentLayout =
                ymaps.templateLayoutFactory.createClass(
                  `<div class="yd-popup-balloon-content">${BX.message(
                    'TWINPX_JS_MULTIPLE_POINTS'
                  )}</div>`
                );

              objectManager = new ymaps.ObjectManager({
                clusterize: true,
                clusterBalloonContentLayout: customBalloonContentLayout,
              });

              objectManager.objects.options.set('iconLayout', 'default#image');
              objectManager.objects.options.set(
                'iconImageHref',
                '/bitrix/images/twinpx.yadelivery/yandexPoint.svg'
              );
              objectManager.objects.options.set('iconImageSize', [32, 42]);
              objectManager.objects.options.set('iconImageOffset', [-16, -42]);
              objectManager.clusters.options.set(
                'preset',
                'islands#blackClusterIcons'
              );
              objectManager.objects.events.add(['click'], onObjectEvent);
              //objectManager.clusters.events.add(['click'], onClusterEvent);

              let firstBound = true;

              if (map) {
                //add object manager
                map.geoObjects.add(objectManager);

                //remove preloader
                elemLoader(document.querySelector('#ydPopupMap'), false);
                //map bounds
                map.setBounds(bounds, {
                  checkZoomRange: true,
                });
                //events
                map.events.add('boundschange', onBoundsChange);
              }

              function onBoundsChange(e) {
                newBounds = e ? e.get('newBounds') : newBounds;

                if (firstBound) {
                  firstBound = false;
                  return;
                }

                //wrapper sorted mode
                ydPopupWrapper.classList.add('yd-popup-list-wrapper--sorted');

                //clear sorted pvz
                for (let key in pointsNodesArray) {
                  if (pointsNodesArray[key]['sorted'] === true) {
                    pointsNodesArray[key]['node'].classList.remove(
                      'yd-popup-list__item--sorted'
                    );
                  }
                }

                //items array
                let arr = pointsArray.filter((point) => {
                  return (
                    point.coords[0] > newBounds[0][0] &&
                    point.coords[0] < newBounds[1][0] &&
                    point.coords[1] > newBounds[0][1] &&
                    point.coords[1] < newBounds[1][1]
                  );
                });

                //set items sorted
                arr.forEach((point) => {
                  let sortedItem = pointsNodesArray[point.id]['node'];
                  pointsNodesArray[point.id]['sorted'] = true;
                  if (sortedItem) {
                    sortedItem.classList.add('yd-popup-list__item--sorted');
                  }
                });
              }

              //send to the server
              (async () => {
                //get offices
                let formData = new FormData();
                formData.set('action', 'getPoints');
                formData.set(
                  'fields',
                  `lat-from=${bounds[0][0]}&lat-to=${bounds[1][0]}&lon-from=${bounds[0][1]}&lon-to=${bounds[1][1]}&payment=${payment}`
                );

                let controller = new AbortController();
                let response;

                setTimeout(() => {
                  if (!response) {
                    controller.abort();
                  }
                }, 20000);

                try {
                  let response = await fetch(window.twinpxYadeliveryFetchURL, {
                    method: 'POST',
                    body: formData,
                  });
                  let result = await response.json();

                  //remove preloader
                  elemLoader(ydPopupWrapper, false);

                  if (result && result.STATUS === 'Y' && result.POINTS) {
                    //fill pointsArray
                    pointsArray = result.POINTS;

                    //list
                    let pointsFlag,
                      objectsArray = [],
                      featureOptions = {};

                    result.POINTS.forEach(
                      ({ id, title, type, schedule, address, coords }) => {
                        if (!id) return;

                        pointsFlag = true;

                        if (pvzId && id === pvzId) {
                          featureOptions.iconImageHref =
                            '/bitrix/images/twinpx.yadelivery/chosenPlacemark.svg';
                          featureOptions.iconImageSize = [48, 63];
                          featureOptions.iconImageOffset = [-24, -63];
                        } else {
                          featureOptions = {};
                        }

                        //placemark
                        objectsArray.push({
                          type: 'Feature',
                          id: id,
                          geometry: {
                            type: 'Point',
                            coordinates: coords,
                          },
                          options: featureOptions,
                        });

                        //list
                        let item = document.createElement('div');
                        item.className = 'yd-popup-list__item';
                        item.setAttribute('data-id', id);

                        item.innerHTML = `
                            <div class="yd-popup-list__title">${title}</div>
                            <div class="yd-popup-list__text">
                              <span>${type}</span> ${schedule}<br>
                              ${address}
                            </div>
                            <div class="twpx-ui-btn">${BX.message(
                              'TWINPX_JS_SELECT'
                            )}</div>
                          `;
                        item.addEventListener('click', (e) => {
                          if (e.target.classList.contains('twpx-ui-btn')) {
                            //click button
                            clickPlacemark(id, address, map, coords);
                          } else if (
                            window.matchMedia('(min-width: 1077px)').matches
                          ) {
                            //pan map on desktop
                            map.panTo(coords).then(() => {
                              map.setZoom(16);
                            });
                          }
                        });
                        ydPopupWrapper.appendChild(item);

                        //push to nodes array
                        pointsNodesArray[id] = {
                          node: item,
                          sorted: false,
                        };
                      }
                    );

                    objectManager.add(objectsArray);

                    if (!pointsFlag) {
                      pointsError();
                    }

                    if (pvzId) {
                      let chosenObject = pointsArray.find(
                        (p) => p.id === pvzId
                      );
                      if (chosenObject) {
                        clickPlacemark(
                          pvzId,
                          chosenObject.address,
                          map,
                          chosenObject.coords
                        );
                      } else {
                        let chosenBtn = ydPopupList.querySelector(
                          '.yd-popup-list__chosen'
                        );
                        chosenBtn.removeEventListener('click', clickChosen);
                        chosenBtn.textContent = `${BX.message(
                          'TWINPX_JS_CHOSEN_ERROR'
                        )} ${chosenAddress ? chosenAddress : ''}`;
                        chosenBtn.className = 'yd-popup-list__chosen-error';
                        ydPopupWrapper.style.height = `calc(100% - ${
                          ydPopupList.querySelector(
                            '.yd-popup-list__chosen-error'
                          ).clientHeight
                        }px - 15px)`;
                      }
                    } else if (
                      ydPopupWrapper.classList.contains(
                        'yd-popup-list-wrapper--sorted'
                      )
                    ) {
                      //if the map was moved while offices were loading
                      onBoundsChange();
                    }

                    //map bounds
                    if (map) {
                      centerCoords = map.getCenter();
                    }
                  } else {
                    pointsError(result.ERRORS);
                  }
                } catch (err) {
                  pointsError();
                }
              })();
            });
          });
        }

        //back button
        ydPopupList
          .querySelector('.yd-popup-list__back')
          .addEventListener('click', (e) => {
            ydPopupContainer.classList.remove('yd-popup--detail');
            ydPopupContainer.classList.remove('yd-popup--map');
            ydPopupContainer.classList.add('yd-popup--list');
            //show sorted
            ydPopupWrapper.classList.add('yd-popup-list-wrapper--sorted');
          });

        //chosen button event
        let chosenBtn = ydPopupList.querySelector('.yd-popup-list__chosen');
        if (chosenBtn) {
          chosenBtn.addEventListener('click', clickChosen);
        }

        function clickChosen(e) {
          e.preventDefault();
          if (pvzId) {
            let chosenObject = pointsArray.find((p) => p.id === pvzId);
            if (chosenObject) {
              clickPlacemark(
                pvzId,
                chosenObject.address,
                map,
                chosenObject.coords
              );
            }
          }
        }
      }
    }
  });

  async function sendOffer(jsonStr) {
    let formData, controller, response;

    formData = new FormData();
    formData.set('action', 'setOfferPriceAdmin');
    formData.set('fields', jsonStr);

    controller = new AbortController();

    setTimeout(() => {
      if (!response) {
        controller.abort();
      }
    }, 20000);

    response = await fetch(window.twinpxYadeliveryFetchURL, {
      method: 'POST',
      body: formData,
    });

    return response.json();
  }
  
  if (ydForm.closest('.yd-popup-content').querySelector('.yd-popup-tabs__tabs__item[data-tab="package"]')) {
	packageForm();
  }
};

window.twinpxYadeliveryPaysystemSelect = function (ydForm) {
  let paysystemSelect = ydForm.querySelector('select');

  if (!paysystemSelect) {
    return;
  }

  paysystemSelect.addEventListener('change', async () => {
    let formData,
      response,
      result,
      orderInput = ydForm.querySelector('[name="ORDER_ID"]');

    formData = new FormData();
    formData.set('action', 'getSumm');
    formData.set('paysystem', paysystemSelect.value);
    formData.set('ORDER_ID', orderInput.value);

    try {
      response = await fetch(window.twinpxYadeliveryFetchURL, {
        method: 'POST',
        body: formData,
      });

      result = await response.json();

      if (result && typeof result === 'object') {
        if (result.STATUS === 'Y' && result.SUMM) {
          let priceInput = ydForm.querySelector('#ydFormPrice');
          if (priceInput) {
            priceInput.value = result.SUMM;
            priceInput.parentNode
              .querySelector('label')
              .classList.add('active');
          }
        }
      }
    } catch (err) {
      //throw err;
    }
  });
};

window.twinpxYadeliveryFillbutton = function (ydForm) {
  ydForm
    .querySelector('.yd-popup-form-fillbutton')
    .addEventListener('click', async (e) => {
      e.preventDefault();

      let formData,
        response,
        result,
        orderInput = ydForm.querySelector('[name="ORDER_ID"]');

      function elemLoader(elem, flag) {
        flag
          ? elem.classList.add('load-circle')
          : elem.classList.remove('load-circle');
      }

      //preloader
      elemLoader(ydForm, true);

      formData = new FormData();
      formData.set('action', 'getOrderData');
      formData.set('id', orderInput.value);

      response = await fetch(window.twinpxYadeliveryFetchURL, {
        method: 'POST',
        body: formData,
      });

      result = await response.json();

      elemLoader(ydForm, false);

      if (result && typeof result === 'object') {
        if (result.STATUS === 'Y') {
          if (result.FIELDS) {
            //fill the form controls
            Object.keys(result.FIELDS).forEach((key) => {
              let formControl = ydForm.querySelector(`[name="${key}"]`);
              if (
                formControl &&
                Boolean(result.FIELDS[key]) &&
                result.FIELDS[key].trim() !== ''
              ) {
                let block = formControl.closest('.b-float-label'),
                  label = block.querySelector('label');
                block.classList.remove('invalid');
                formControl.value = result.FIELDS[key];
                label ? label.classList.add('active') : undefined;
              }
            });
			
			if (result.BOXES && result.PRODUCTS) {
				const packageTab = ydForm.closest('.yd-popup-content').querySelector('.yd-popup-tabs__tabs__item[data-tab="package"]');
				const packageNavTab = ydForm.closest('.yd-popup-content').querySelector('.yd-popup-tabs__nav__item[data-tab="package"]');
				
				packageNavTab.classList.remove('yd-popup-tabs__nav__item--hidden');
				
				if (packageTab) {
					packageTab.innerHTML = packageTabHtml(result);
					packageForm();
				}
			}
          }
        } else {
          orderInput.focus();
          orderInput.closest('.b-float-label').classList.add('invalid');
        }
      }
    });
};

window.twinpxYadeliveryPopupSettings = {
  width: 'auto',
  height: 'auto',
  min_width: 300,
  min_height: 300,
  zIndex: 100,
  autoHide: true,
  offsetTop: 1,
  offsetLeft: 0,
  lightShadow: true,
  closeIcon: true,
  closeByEsc: true,
  draggable: {
    restrict: false,
  },
  overlay: {
    backgroundColor: 'black',
    opacity: '80',
  },
};

function packageTabHtml(data) {
	
	let boxes = '';
	
	if (data.BOXES && data.BOXES.forEach) {
		data.BOXES.forEach((box) => {
			let boxControl = '';
			let cxControl = '';
			let cyControl = '';
			let czControl = '';
			let wgControl = '';
			
			if (box.controls && box.controls.forEach) {
				box.controls.forEach((control) => {
					if (control.property === 'select') {
						let options = '';
						
						if (control.options && control.options.forEach) {
							control.options.forEach((optionItem) => {
								options += `<option value="${optionItem.code}"${String(optionItem.code) === String(control.value) ? ' selected' : ''}${optionItem.custom === true ? ' data-custom="true"' : ''}>${optionItem.label}</option>`;
							});
						}
						boxControl = `
							<div class="twpx-ydw-order-form-control twpx-ydw-order-form-control--active">
								<div class="twpx-ydw-order-label">${control.label}</div>
								<select name="${control.name}" size="1" id="box" class="twpx-ydw-order-select">
									${options}
								</select>
							</div>
						`;
					}
					else if (control.property === 'text') {
						if (control.name.includes('cx')) {
							cxControl = `
								<div class="twpx-ydw-order-form-control">
									<div class="twpx-ydw-order-label">${control.label}</div>
									<input type="text" name="${control.name}" data-name="length" value="${control.value}" class="twpx-ydw-order-input">
								</div>
							`;
						}
						else if (control.name.includes('cy')) {
							cyControl = `
								<div class="twpx-ydw-order-form-control">
									<div class="twpx-ydw-order-label">${control.label}</div>
									<input type="text" name="${control.name}" data-name="width" value="${control.value}" class="twpx-ydw-order-input">
								</div>
							`;
						}
						else if (control.name.includes('cz')) {
							czControl = `
								<div class="twpx-ydw-order-form-control">
									<div class="twpx-ydw-order-label">${control.label}</div>
									<input type="text" name="${control.name}" data-name="height" value="${control.value}" class="twpx-ydw-order-input">
								</div>
							`;
						}
						else if (control.name.includes('wg')) {
							wgControl = `
								<div class="twpx-ydw-order-form-control">
									<div class="twpx-ydw-order-label">${control.label}</div>
									<input type="text" name="${control.name}" data-name="weight" value="${control.value}" class="twpx-ydw-order-input">
								</div>
							`;
						}
					}
				});
			}
			
			boxes += `
				<div class="twpx-ydw-order-form-block-content">
					<div class="twpx-ydw-order-btn-remove">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
							<rect width="24" height="24" rx="8" fill="#fb3f1d"></rect>
							<g transform="translate(7.125 6)">
								<path d="M18.828,10.1H16.765V9.726A1.125,1.125,0,0,0,15.64,8.6h-1.5a1.125,1.125,0,0,0-1.125,1.125V10.1H10.952a.937.937,0,0,0-.937.938v.75a.374.374,0,0,0,.375.375h9a.375.375,0,0,0,.375-.375v-.75A.938.938,0,0,0,18.828,10.1Zm-5.063-.375a.375.375,0,0,1,.375-.375h1.5a.375.375,0,0,1,.375.375V10.1h-2.25Z" transform="translate(-10.015 -8.601)" fill="#fff"></path>
								<path d="M10.863,13.909a.117.117,0,0,0-.117.123l.31,6.493A1.124,1.124,0,0,0,12.179,21.6h5.695A1.124,1.124,0,0,0,19,20.524l.31-6.493a.117.117,0,0,0-.117-.123Zm5.664.937a.375.375,0,0,1,.75,0v4.875a.375.375,0,1,1-.75,0Zm-1.875,0a.375.375,0,0,1,.75,0v4.875a.375.375,0,1,1-.75,0Zm-1.875,0a.375.375,0,0,1,.75,0v4.875a.375.375,0,1,1-.75,0Z" transform="translate(-10.152 -9.596)" fill="#fff"></path>
							</g>
						</svg>
					</div>
					<div class="twpx-ydw-order-form-block-description"><b>${box.heading}</b></div>
					<div class="twpx-ydw-order-form-group">
						<div class="twpx-ydw-order-form-wrapper">
							${boxControl}
							<div class="twpx-ydw-order-form-control-custom">
								<div class="twpx-ydw-order-form-control-dimensions">
									${cxControl}
									${cyControl}
									${czControl}
								</div>
								${wgControl}
							</div>
						</div>
					</div>
				</div>`;
		});
	}
	
	let products = '';
	
	if (data.PRODUCTS && data.PRODUCTS.forEach) {
		data.PRODUCTS.forEach((product) => {
			
			let productControl = '';
			let boxControl = '';
			
			if (product.controls && product.controls.forEach) {
				product.controls.forEach((control) => {
					
					if (control.property === 'text') {
            const value = String(control.value).replace(/\"/g, '&quot;');
						productControl = `
							<div class="twpx-ydw-order-form-control twpx-ydw-order-form-control--active">
								<div class="twpx-ydw-order-label">${control.label}</div>
								<input type="text" name="${control.name}" value="${value}" class="twpx-ydw-order-input" disabled="">
							</div>
						`;
					} else if (control.property === 'select') {
						let options = '';
						
						if (control.options && control.options.forEach) {
							control.options.forEach((optionItem) => {
								options += `<option value="${optionItem.code}"${String(optionItem.code) === String(control.value) ? ' selected' : ''}>${optionItem.label}</option>`;
							});
						}
						
						boxControl = `
							<div class="twpx-ydw-order-form-control twpx-ydw-order-form-control--active twpx-ydw-order-form-control--product-box">
								<div class="twpx-ydw-order-label">${control.label}</div>
								<select name="${control.name}" class="twpx-ydw-order-select">${options}</select>
								<div class="twpx-ydw-order-form-note">${control.hint_external}</div>
							</div>
						`;
					}
				});
			}
			
			products += `
				<div class="twpx-ydw-order-form-block-content">
					<div class="twpx-ydw-order-form-block-description"><b>${product.heading}</b></div>
					<div class="twpx-ydw-order-form-group">
						<div class="twpx-ydw-order-form-wrapper">
							${productControl}
							${boxControl}
						</div>
					</div>
				</div>
			`;
		});
	}
	
    return `
		<div id="twinpxYadeliveryBoxes" class="twpx-ydw-order-form-block" data-barcode="someBarcode">
			<div class="twpx-ydw-order-form-block-title">${BX.message('TWINPX_MODAL_PACKAGE')}</div>
			<div class="twpx-ydw-order-form-block-text">${BX.message('Box_desc')}</div>
			${boxes}
			<div class="twpx-ydw-order-add-button">${BX.message('Add_box')}</div>
		</div>
		<div id="twinpxYadeliveryProducts" class="twpx-ydw-order-form-block">
			<div class="twpx-ydw-order-form-block-title">${BX.message('Product_header')}</div>
			<div class="twpx-ydw-order-form-block-text">${BX.message('Product_desc')}</div>
			${products}
		</div>
	`;
}

function packageForm() {
	const boxesBlock = document.querySelector('#twinpxYadeliveryBoxes');
	const productsBlock = document.querySelector('#twinpxYadeliveryProducts');

	const storage = {
		boxes: {
			id: null,
			value: {
				checked: null,
				customSize: {},
			},
		},
		from: {
			id: null,
			value: {},
		},
	};

	// set initial values for the storage
	(() => {
		const blocks = boxesBlock.querySelectorAll('.twpx-ydw-order-form-block-content');
		const lastBlock = blocks[blocks.length - 1];

		// checked
		storage.boxes.value.checked = lastBlock.querySelector('select').value;

		// customSize
		lastBlock.querySelector('.twpx-ydw-order-form-control-custom').querySelectorAll('input[type="text"]').forEach(control => {
			storage.boxes.value.customSize[control.getAttribute('data-name')] = control.value;
		});
	})();

	function inputEvents(control, block) {
		if (control.value && control.value.trim() !== '') {
		  block.classList.add('twpx-ydw-order-form-control--active');
		}

		control.addEventListener('focus', () => {
		  block.classList.add('twpx-ydw-order-form-control--active');
		});

		control.addEventListener('blur', () => {
		  if (control.value.trim() !== '') {
			block.classList.add('twpx-ydw-order-form-control--active');
		  } else {
			block.classList.remove('twpx-ydw-order-form-control--active');
		  }
		  //check required
		  if (control.getAttribute('required')) {
			if (control.value.trim() === '') {
			  block.classList.add('twpx-ydw-order-form-control--invalid');
			  isFormValid = false;
			} else {
			  block.classList.remove('twpx-ydw-order-form-control--invalid');
			}
			// validateForm();
			disabledPeriodSelects();
			setOrderButtonActive();
		  }
		});

		//this is not required, because we have document onclick
		//just to be sure
		control.addEventListener('keyup', () => {
		//   hideError();
		});

		//boxes custom
		if (
		  control.closest('.twpx-ydw-order-form-block') &&
		  control.closest('.twpx-ydw-order-form-block').id ===
			'twinpxYadeliveryBoxes'
		) {
		  control.addEventListener('keyup', (e) => {
			storage.boxes.value.customSize[control.getAttribute('data-name')] =
			  control.value;
			// setBX24Storage(
			//   storage.boxes.id,
			//   JSON.stringify(storage.boxes.value)
			// );
		  });
		}

		//list
		const listButton = block.querySelector(
		  '.twpx-ydw-order-form-control--map .twpx-ydw-order-input'
		);

		if (listButton) {
		  listButton.addEventListener('click', (e) => {
			e.preventDefault();

			const input = listButton
			  .closest('.twpx-ydw-order-form-control')
			  .querySelector('.twpx-ydw-order-input');

			const hiddenInput = listButton
			  .closest('.twpx-ydw-order-form-control')
			  .querySelector('input[type="hidden"]');

			const json =
			  hiddenInput.value.trim() !== ''
				? JSON.parse(hiddenInput.value)
				: {};

			input.setAttribute('data-active', true);

			if (input.value.trim() !== '') {
			  //open map
			  document.dispatchEvent(
				new CustomEvent('twpxYdwInitMap', {
				  detail: json,
				})
			  );
			} else {
			  //open location
			  document.dispatchEvent(new CustomEvent('twpxYdwInitLocation'));
			}
		  });
		}

		//calc
		const calcButton = block.querySelector('.twpx-ydw-order-btn-calc');

		if (calcButton) {
		  const paymentInput = orderBlock.querySelector('#twpxYdwPaymentInput');

		  calcButton.addEventListener('click', async (e) => {
			e.preventDefault();

			calcButton.classList.add('twpx-ydw-order-btn--loading');

			let formData = new FormData(orderForm),
			  controller = new AbortController(),
			  response,
			  result;

			setTimeout(() => {
			  if (!response) {
				controller.abort();
				showError(
				  'Connection aborted.',
				  block.closest('.twpx-ydw-order-form-block')
				);
			  }
			}, 20000);

			try {
			  response = await fetch(paymentInput.getAttribute('data-url'), {
				method: 'POST',
				body: formData,
				signal: controller.signal,
			  });

			  result = await response.json();

			  calcButton.classList.remove('twpx-ydw-order-btn--loading');

			  if (result && typeof result === 'object') {
				if (result.status === 'success') {
				  if (String(result.data.num)) {
					setInputValue(
					  paymentInput.querySelector('input'),
					  result.data.num
					);
					paymentInput
					  .querySelector('input')
					  .dispatchEvent(new Event('blur'));
				  }
				} else if (result.errors) {
				  calcButton.classList.remove('twpx-ydw-order-btn--loading');

				  showError(
					result.errors[0].message,
					block.closest('.twpx-ydw-order-form-block')
				  );
				}
			  }
			} catch (err) {
			  calcButton.classList.remove('twpx-ydw-order-btn--loading');

			  showError(err, block.closest('.twpx-ydw-order-form-block'));
			}
		  });
		}

		//close
		const closeButton = block.querySelector('.twpx-ydw-order-form-close');

		if (closeButton) {
		  closeButton.addEventListener('click', async (e) => {
			e.preventDefault();

			setInputValue(control, '');

			const hidden =
			  control.parentNode.querySelector('input[type=hidden]');
			hidden.value = '';

			const textDiv = control.parentNode.querySelector(
			  '.twpx-ydw-order-form-control-text'
			);
			if (textDiv) {
			  textDiv.style.display = 'none';
			  textDiv.textContent = '';
			}

			// validateForm();
			disabledPeriodSelects();
			setOrderButtonActive();
		  });
		}
	}

	function setInputValue(input, value) {
    const block = input.closest('.twpx-ydw-order-form-control');
    input.value !== undefined
      ? (input.value = value)
      : (input.textContent = value);

    if (String(value).trim() !== '') {
      block.classList.add('twpx-ydw-order-form-control--active');
    } else {
      block.classList.remove('twpx-ydw-order-form-control--active');
    }
  }

	//boxes block
    let boxesIndex = 0;
    const boxesArray = []; //array to set boxes in order
    const boxTitle = boxesBlock
      .querySelector('.twpx-ydw-order-form-block-description b')
      .textContent.trim()
      .split(' ')[0];
    const customOption = boxesBlock.querySelector('[data-custom="true"]');
    const customOptionValue =
      customOption &&
      (customOption.getAttribute('value') ||
        customOption.getAttribute('data-value'));

    //create block template for adding
    const blockForAdding = createBlockForAdding();

    //on page load - show/hide custom block
    boxesBlock
      .querySelectorAll('.twpx-ydw-order-form-block-content')
      .forEach((box) => {
        const select =
          box.querySelector('select') ||
          window.twpxSelectManager.selectObject[
            box.querySelector('.twpx-select').getAttribute('data-id')
          ];
        if (storage.boxes.value.checked) {
          select.value = storage.boxes.value.checked;
        }
        if (Object.keys(storage.boxes.value.customSize).length) {
          box
            .querySelectorAll('.twpx-ydw-order-input')
            .forEach((control) => {
              const value =
                storage.boxes.value.customSize[
                  control.getAttribute('data-name')
                ];

              if (value) {
                control.value = value;
                control
                  .closest('.twpx-ydw-order-form-control')
                  .classList.add('twpx-ydw-order-form-control--active');
              }
            });
        }
        const selectValue = select.value;
        const customBlock = box.querySelector(
          '.twpx-ydw-order-form-control-custom'
        );
        if (selectValue === customOptionValue) {
          customBlock.style.display = 'grid';
        } else {
          customBlock.style.display = 'none';
        }
      });

    //box counter
    boxesBlock
      .querySelectorAll('.twpx-ydw-order-form-block-content')
      .forEach((box) => {
        boxesCounter(box);
      });

    //products box select
    createProductsSelect();

    //select events
    selectEvents(boxesBlock);

    //add button
    boxesBlock
      .querySelectorAll('.twpx-ydw-order-add-button')
      .forEach((addButton) => {
        addButton.addEventListener('click', (e) => {
          e.preventDefault();

          const newBlock = blockForAdding.cloneNode(true);

          //twpx select
          newBlock.querySelectorAll('.twpx-select').forEach((select) => {
            new twpxSelect({
              select,
              checked: storage.boxes.value.checked,
            });
            showCustomControls(select, storage.boxes.value.checked);
          });
          //select
          newBlock.querySelectorAll('select').forEach((select) => {
            select.value = storage.boxes.value.checked;
            showCustomControls(select, storage.boxes.value.checked);
          });

          addButton.before(newBlock);
          boxesCounter(newBlock);
          createProductsSelect(null, null);
          showDeleteButtons(boxesBlock);

          newBlock
            .querySelectorAll('.twpx-ydw-order-input')
            .forEach((control) => {
              if (control.getAttribute('data-name')) {
                const value =
                  storage.boxes.value.customSize[
                    control.getAttribute('data-name')
                  ];

                if (value) {
                  control.value = value;
                }
              }

              inputEvents(
                control,
                control.closest('.twpx-ydw-order-form-control')
              );
            });

          selectEvents(newBlock);
        //   fitWindow();
        });
      });

    //delete button
    boxesBlock.addEventListener('click', (e) => {
      if (e.target.classList.contains('twpx-ydw-order-btn-remove')) {
        const content = e.target.closest(
          '.twpx-ydw-order-form-block-content'
        );
        const indexToRemove = content.getAttribute('data-index');
        const currentIndex = boxesArray.findIndex(
          (el) => String(el) === String(indexToRemove)
        );

        content.remove();

        boxesArray.splice(currentIndex, 1);

        createProductsSelect(indexToRemove, currentIndex);
        showDeleteButtons(boxesBlock);

        // validateForm();
        disabledPeriodSelects();
        setOrderButtonActive();
      }
    });

    function selectEvents(block) {
      if (block.querySelector('.twpx-select')) {
        block.querySelectorAll('.twpx-select').forEach((boxesSelect) => {
          const boxesTwpxSelect =
            window.twpxSelectManager.selectObject[
              boxesSelect.getAttribute('data-id')
            ];
          boxesTwpxSelect.onChange = () => {
            showCustomControls(boxesSelect, boxesTwpxSelect.value);
            //storage
            storage.boxes.value.checked = boxesTwpxSelect.value;
            // setBX24Storage(
            //   storage.boxes.id,
            //   JSON.stringify(storage.boxes.value)
            // );
            // fitWindow();
          };
        });
      } else {
        block.addEventListener('change', (e) => {
          if (e.target.tagName.toLowerCase() === 'select') {
            showCustomControls(e.target, e.target.value);
            //storage
            storage.boxes.value.checked = e.target.value;
            // setBX24Storage(
            //   storage.boxes.id,
            //   JSON.stringify(storage.boxes.value)
            // );
          }
        });
      }
    }

    function showCustomControls(select, value) {
      const customBlock = select
        .closest('.twpx-ydw-order-form-wrapper')
        .querySelector('.twpx-ydw-order-form-control-custom');

      if (value === customOptionValue) {
        customBlock.style.display = 'grid';
      } else {
        customBlock.style.display = 'none';
      }
    }

    function showDeleteButtons(block) {
      if (
        block.querySelectorAll('.twpx-ydw-order-form-block-content')
          .length > 1
      ) {
        block.classList.add('twpx-ydw-order-form-block--multiple');
      } else {
        block.classList.remove('twpx-ydw-order-form-block--multiple');
      }
    }

    function createBlockForAdding() {
      const blockForAdding = boxesBlock
        .querySelector('.twpx-ydw-order-form-block-content')
        .cloneNode(true);
      const customBlock = blockForAdding.querySelector(
        '.twpx-ydw-order-form-control-custom'
      );

      blockForAdding
        .querySelectorAll('.twpx-ydw-order-form-control')
        .forEach((formControl) => {
          formControl.classList.remove(
            'twpx-ydw-order-form-control--invalid'
          );
          const input = formControl.querySelector('.twpx-ydw-order-input ');
          const select = formControl.querySelector(
            '.twpx-ydw-order-select '
          );
          const twpxSelectElement =
            formControl.querySelector('.twpx-select');

          if (input) {
            input.value = '';
            inputEvents(input, formControl);
            formControl.classList.remove(
              'twpx-ydw-order-form-control--active'
            );
          } else if (select) {
            if (select.value === customOptionValue) {
              customBlock.style.display = 'grid';
            } else {
              customBlock.style.display = 'none';
            }
          } else if (twpxSelectElement) {
            if (
              twpxSelectElement.querySelector('[type="hidden"]').value ===
              customOptionValue
            ) {
              customBlock.style.display = 'grid';
            } else {
              customBlock.style.display = 'none';
            }
          }
        });
      return blockForAdding;
    }

    function boxesCounter(boxContainer) {
      //boxesArray
      boxesArray.push(boxesIndex);
      //index
      boxContainer.setAttribute('data-index', boxesIndex);
      boxesIndex++;
      //title
      boxContainer.querySelector(
        '.twpx-ydw-order-form-block-description b'
      ).textContent = `${boxTitle} ${boxesIndex}`;
      //name attribute
      boxContainer.querySelectorAll('[name]').forEach((control) => {
        let name = control.getAttribute('name');
        control.setAttribute('name', name.replace(/\d/g, boxesIndex));
      });
    }

    function createProductsSelect(indexToRemove, currentIndex) {
      //productSelect
      //select
      productsBlock
        .querySelectorAll(
          '.twpx-ydw-order-form-control--product-box select'
        )
        .forEach((select) => {
          let value = select.value;
          let options = ``;

          if (currentIndex === 0) {
            value = 1 * boxesArray[0] + 1;
          } else if (
            indexToRemove &&
            String(value) === String(1 * indexToRemove + 1)
          ) {
            value = 1 * boxesArray[currentIndex - 1] + 1;
          }

          boxesArray.forEach((boxIndex) => {
            let selected = ``;
            if (String(1 * boxIndex + 1) === String(value)) {
              selected = ` selected`;
            }
            options += `<option value="${1 * boxIndex + 1}"${selected}>${
              1 * boxIndex + 1
            }</option>`;
          });
          select.innerHTML = options;
        });

      //twpxSelect
      productsBlock
        .querySelectorAll(
          '.twpx-ydw-order-form-control--product-box .twpx-select'
        )
        .forEach((select) => {
          const productsTwpxSelect =
            window.twpxSelectManager.selectObject[
              select.getAttribute('data-id')
            ];
          let value = productsTwpxSelect.value;

          if (currentIndex === 0 || currentIndex === undefined) {
            value = String(1 * boxesArray[0] + 1);
          } else if (
            indexToRemove &&
            String(value) === String(1 * indexToRemove + 1)
          ) {
            value = String(1 * boxesArray[currentIndex - 1] + 1);
          }

          let arr = [];
          boxesArray.forEach((boxIndex) => {
            arr.push({
              code: String(1 * boxIndex + 1),
              name: String(1 * boxIndex + 1),
            });
          });

          productsTwpxSelect.recreate({ options: arr, val: value });
        });
    }
}

//function from twinpx_delivery_offers.php
function newOffer(id) {
  function offersError(error) {
    document.getElementById(
      'popup-window-content-newOffer'
    ).innerHTML = `<div class="yd-popup-error__message"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>${error}</div>`;
  }

  var Confirmer = new BX.PopupWindow('newOffer', null, {
    content: `<div id="context_${id}"><div id="showOffer_${id}" class="loading list__offer">${BX.message(
      'TWINPX_JS_LOADING'
    )}</div></div>`,
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_SELECT_PERIOD')), //BX.create("span", {html: BX.message('TWINPX_JS_SELECT_PERIOD'), 'props': {'className': 'popup-window-titlebar-text'}})
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: async function () {
        function newOfferElemLoader(flag) {
          flag
            ? newOfferElem.classList.add('load-circle')
            : newOfferElem.classList.remove('load-circle');
        }

        function createOffersHtml(offersArray) {
          let selectMessage = window.BX
              ? window.BX.message('TWINPX_JS_SELECT')
              : 'Choose',
            html = `<div class="yd-popup-offers__wrapper">`;

          offersArray.forEach(({ json, date, time, price }) => {
            html += `<div class="yd-popup-offers__item" data-json='${json}'>
                  <div class="yd-popup-offers__info">
                      <span class="yd-popup-offers__date"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/pvz-calendar.svg)"></i>${date}</span>
                      <span class="yd-popup-offers__time"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/pvz-clock.svg)"></i>${time}</span>
                    </div>
                  <b class="yd-popup-offers__price">${price}</b>
                  <a href="#" class="twpx-ui-btn">${selectMessage}</a>
                </div>`;
          });

          html += '</div>';

          return html;
        }

        document
          .getElementById('popup-window-content-newOffer')
          .addEventListener('click', (e) => {
            if (
              e.target.classList.contains('yd-popup-offers__item') ||
              e.target.closest('.yd-popup-offers__item')
            ) {
              let itemNode = e.target.classList.contains(
                  'yd-popup-offers__item'
                )
                  ? e.target
                  : e.target.closest('.yd-popup-offers__item'),
                fields = itemNode.getAttribute('data-json');

              setPrice(fields, id);
              Confirmer.destroy();
            }
          });

        let formData = new FormData(),
          controller = new AbortController(),
          response,
          result,
          html = '';

        //fetch request
        formData.set('action', 'new');
        formData.set('itemID', id);

        setTimeout(() => {
          if (!response) {
            controller.abort();
          }
        }, 20000);

        try {
          response = await fetch(window.twinpxYadeliveryFetchURL, {
            method: 'POST',
            body: formData,
            signal: controller.signal,
          });

          result = await response.json();

          if (result && typeof result === 'object') {
            if (result.STATUS === 'Y') {
              if (result.ERRORS) {
                offersError(result.ERRORS);
              } else {
                if (result.OFFERS) {
                  newOfferElem = document.getElementById(`showOffer_${id}`);
                  //remove preloader
                  newOfferElemLoader(false);

                  //html
                  html = createOffersHtml(result.OFFERS);
                  newOfferElem.innerHTML = html;

                  //effect
                  setTimeout(() => {
                    showOfferElem.classList.add('yd-popup-offers--animate');
                  }, 0);

                  Confirmer.adjustPosition();
                } else {
                  offersError(BX.message('TWINPX_JS_EMPTY_OFFER'));
                }
              }
            } else {
              offersError(BX.message('TWINPX_JS_NO_RESPONSE'));
            }
          }
        } catch (err) {
          offersError(BX.message('TWINPX_JS_NO_RESPONSE'));
        }
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
      },
    },
    buttons: [
      new BX.PopupWindowButton({
        text: BX.message('TWINPX_JS_CLOSE'),
        className: 'link-cancel',
        events: {
          click: function () {
            this.popupWindow.close();
            document.location.reload();
          },
        },
      }),
    ],
  });
  Confirmer.show();
}

function updateOffer(id) {
  var Confirmer = new BX.PopupWindow(`update${id}`, null, {
    content: `<div id="context_${id}"><div id="showOffer_${id}" class="loading">${BX.message(
      'TWINPX_JS_LOADING'
    )}</div></div>`,
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_UPDATES')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        $.post(
          window.twinpxYadeliveryFetchURL,
          { itemID: id, action: 'update' },
          function (data) {
            node = document.getElementById(`showOffer_${id}`);
            node.innerHTML = data;
            node.classList.remove('loading');

            Confirmer.adjustPosition();
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
      },
    },
    buttons: [
      new BX.PopupWindowButton({
        text: BX.message('TWINPX_JS_CLOSE'),
        className: 'link-cancel',
        events: {
          click: function () {
            this.popupWindow.close();
            document.location.reload();
          },
        },
      }),
    ],
  });
  Confirmer.show();
}

function cancelOffer(id) {
  var Confirmer = new BX.PopupWindow(`cancel_${id}`, null, {
    content: `<div id="cancel_context_${id}"><div id="showOffer_${id}" class="loading">${BX.message(
      'TWINPX_JS_LOADING'
    )}</div></div>`,
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_CANCEL')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        $.post(
          window.twinpxYadeliveryFetchURL,
          { itemID: id, action: 'cancel' },
          function (data) {
            node = document.getElementById(`showOffer_${id}`);
            node.innerHTML = data;
            node.classList.remove('loading');

            Confirmer.adjustPosition();
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
      },
    },
    buttons: [
      new BX.PopupWindowButton({
        text: BX.message('TWINPX_JS_CLOSE'),
        className: 'link-cancel',
        events: {
          click: function () {
            this.popupWindow.close();
            document.location.reload();
          },
        },
      }),
    ],
  });
  Confirmer.show();
}

function printBarcode(id) {
  var Confirmer = new BX.PopupWindow(`barcode${id}`, null, {
    content: `<div id="context_${id}"><div id="printBarcode_${id}" class="loading">${BX.message(
      'TWINPX_JS_LOADING'
    )}</div></div>`,
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_BARKOD')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        $.post(
          window.twinpxYadeliveryFetchURL,
          { itemID: id, action: 'barcode' },
          function (data) {
            node = document.getElementById(`printBarcode_${id}`);
            node.innerHTML = data;
            node.classList.remove('loading');

            Confirmer.adjustPosition();
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
      },
    },
    buttons: [
      new BX.PopupWindowButton({
        text: BX.message('TWINPX_JS_CLOSE'),
        className: 'link-cancel',
        events: {
          click: function () {
            this.popupWindow.close();
            document.location.reload();
          },
        },
      }),
    ],
  });
  Confirmer.show();
}

function printDocument(id) {
  var Confirmer = new BX.PopupWindow(`document${id}`, null, {
    content: `<div id="context_${id}"><div id="printDocument_${id}" class="loading">${BX.message(
      'TWINPX_JS_LOADING'
    )}</div></div>`,
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_ACT')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        $.post(
          window.twinpxYadeliveryFetchURL,
          { itemID: id, action: 'document' },
          function (data) {
            node = document.getElementById(`printDocument_${id}`);
            node.innerHTML = data;
            node.classList.remove('loading');

            Confirmer.adjustPosition();
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
      },
    },
    buttons: [
      new BX.PopupWindowButton({
        text: BX.message('TWINPX_JS_CLOSE'),
        className: 'link-cancel',
        events: {
          click: function () {
            this.popupWindow.close();
            document.location.reload();
          },
        },
      }),
    ],
  });
  Confirmer.show();
}

function updateAll() {
  var Confirmer = new BX.PopupWindow('updateAll', null, {
    content: `<div id="context"><div id="update_content" class="loading">${BX.message(
      'TWINPX_JS_LOADING'
    )}</div></div>`,
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_UPDATE')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        $.post(
          window.twinpxYadeliveryFetchURL,
          { action: 'updateAll' },
          function (data) {
            node = document.getElementById(`update_content`);
            node.innerHTML = data;
            node.classList.remove('loading');
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
        document.location.reload();
      },
    },
    buttons: [
      new BX.PopupWindowButton({
        text: BX.message('TWINPX_JS_CLOSE'),
        className: 'link-cancel',
        events: {
          click: function () {
            this.popupWindow.close();
            document.location.reload();
          },
        },
      }),
    ],
  });
  Confirmer.show();
}

function archiveOffer(id) {
  var Confirmer = new BX.PopupWindow(`archive_${id}`, null, {
    content: `<div id="archive_context_${id}"><div id="showOffer_${id}" class="loading">${BX.message(
      'TWINPX_JS_LOADING'
    )}</div></div>`,
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_ARHIVE')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        $.post(
          window.twinpxYadeliveryFetchURL,
          { itemID: id, action: 'archive' },
          function (data) {
            node = document.getElementById(`showOffer_${id}`);
            node.innerHTML = data;
            node.classList.remove('loading');

            Confirmer.adjustPosition();
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
      },
    },
    buttons: [
      new BX.PopupWindowButton({
        text: BX.message('TWINPX_JS_CLOSE'),
        className: 'link-cancel',
        events: {
          click: function () {
            this.popupWindow.close();
            document.location.reload();
          },
        },
      }),
    ],
  });
  Confirmer.show();
}

async function setPrice(fields, id) {
  let formData = new FormData();
  formData.set('action', 'offer');
  formData.set('fields', fields);
  formData.set('id', id);

  await fetch(window.twinpxYadeliveryFetchURL, {
    method: 'POST',
    body: formData,
  });

  document.location.reload();
}

function newDelivery(orderId) {
  function elemLoader(elem, flag) {
    flag
      ? elem.classList.add('load-circle')
      : elem.classList.remove('load-circle');
  }

  window.ydConfirmer = new BX.PopupWindow('newDelivery', null, {
    content:
      '<div id="newDeliveryContent" class="yd-popup-content load-circle"></div>',
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_CREATE')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        pageScroll(false);
        $.post(
          window.twinpxYadeliveryFetchURL,
          { action: 'newDelivery', id: orderId },
          function (data) {
            node = document.getElementById(`newDeliveryContent`);
            node.innerHTML = createNewDeliveryHtml(data);
            elemLoader(node, false);
            window.ydConfirmer.adjustPosition();
            newDeliveryPopupOnload();
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
        pageScroll(true);
      },
    },
    buttons: [],
  });
  window.ydConfirmer.show();
}

function newDeliveryPvz(orderId, pvzId, chosenAddress) {
  function elemLoader(elem, flag) {
    flag
      ? elem.classList.add('load-circle')
      : elem.classList.remove('load-circle');
  }

  window.ydConfirmerPvz = new BX.PopupWindow('newDeliveryPvz', null, {
    content:
      '<div id="newDeliveryContentPvz" class="yd-popup-content load-circle"></div>',
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_CREATE')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        pageScroll(false);

        //show error if there is no api ymaps key
        if (!window.twinpxYadeliveryYmapsAPI) {
          document
            .querySelector('#newDeliveryContentPvz')
            .classList.remove('load-circle');
          document.querySelector(
            '#newDeliveryContentPvz'
          ).innerHTML = `<div class="yd-popup-error__message">
            <i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>
            ${BX.message('TWINPX_JS_NO_YMAP_KEY')}
          </div>`;

          return;
        }

        $.post(
          window.twinpxYadeliveryFetchURL,
          { action: 'newDeliveryPvz', id: orderId },
          function (data) {
            node = document.getElementById(`newDeliveryContentPvz`);
            node.innerHTML = createNewDeliveryPvzHtml(data);
            elemLoader(node, false);
            window.ydConfirmerPvz.adjustPosition();
            newDeliveryPvzPopupOnload(orderId, pvzId, chosenAddress);
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
        pageScroll(true);
      },
    },
  });
  window.ydConfirmerPvz.show();
}

function createNewDeliveryHtml(data) {
	let fieldsHidden = '';
	
	if (data.FIELDS_HIDDEN && typeof data.FIELDS_HIDDEN === 'object') {
		Object.entries(data.FIELDS_HIDDEN).forEach((entry) => {
			fieldsHidden += `<input type="hidden" id="${entry[0]}" name="${entry[0]}" value="${entry[1]}">`;
		});
	}
	let paymentOptions = '';
	
	if (data.PAYMENT_TYPE && typeof data.PAYMENT_TYPE === 'object') {
		Object.entries(data.PAYMENT_TYPE).forEach((entry) => {
			paymentOptions += `<option value="${entry[0]}"${data.FIELDS && data.FIELDS.PAY_TYPE && data.FIELDS.PAY_TYPE===entry[0] ? 'selected="selected"' : ''}>${entry[1]}</option>`;
		});
	}
	
	const packageTab = packageTabHtml(data);
	
	return `
    <div class="yd-popup-error"></div>
    <div class="yd-popup-body">
        <div class="yd-popup-tabs">
            <div class="yd-popup-tabs__nav">
                <div class="yd-popup-tabs__nav__item yd-popup-tabs__nav__item--active" data-tab="general">${BX.message('TWINPX_MODAL_GENERAL')}</div>
				<div class="yd-popup-tabs__nav__item${(!data.BOXES || !data.BOXES.length || !data.PRODUCTS || !data.PRODUCTS.length) ? ' yd-popup-tabs__nav__item--hidden' : ''}" data-tab="package">${BX.message('TWINPX_MODAL_PACKAGE')}</div>
            </div>
            <div class="yd-popup-tabs__tabs">
                <form action="" novalidate="">
                    ${fieldsHidden}
                    <div class="yd-popup-tabs__tabs__item yd-popup-tabs__tabs__item--active" data-tab="general">
                        <div class="yd-popup-form">
                            <div class="yd-popup-form__col">
                                <div class="b-float-label">
                                    <input name="ORDER_ID" id="ydFormOrder" type="number" min="1" value="${data.FIELDS.ORDER_ID}"${data.FIELDS.ORDER_ID ? ' readonly=""' : ''} required="">
                                    <label for="ydFormOrder"${data.FIELDS.ORDER_ID ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_ORDER')}*</label>
                                    <div class="yd-popup-form-fillbutton">${BX.message('TWINPX_YADELIVERY_GETDATA')}</div>
                                </div>
                                <div class="b-float-label">
                                    <input name="PropFio" id="ydFormFio" type="text" value="${data.FIELDS.PropFio}" required="">
                                    <label for="ydFormFio"${data.FIELDS.PropFio ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_FIO')}*</label>
                                </div>
                                <div class="b-float-label">
                                    <input name="PropEmail" id="ydFormEmail" type="email" value="${data.FIELDS.PropEmail}">
                                    <label for="ydFormEmail"${data.FIELDS.PropEmail ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_EMAIL')}</label>
                                </div>
                                <div class="b-float-label">
                                    <input name="PropPhone" id="ydFormPhone" type="tel" value="${data.FIELDS.PropPhone}" required="">
                                    <label for="ydFormPhone"${data.FIELDS.PropPhone ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_PHONE')}*</label>
                                </div>
                                <div class="b-form-control b-float-label">
                                    <select name="PAY_TYPE" id="ydFormPay" required="">
                                        ${paymentOptions}
                                    </select>
                                </div>
                                <div class="b-float-label">
                                    <input name="PropPrice" id="ydFormPrice" type="number" min="0" value="${data.FIELDS.PropPrice}" required="">
                                    <label for="ydFormPrice"${data.FIELDS.PropPrice ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_COST')}</label>
                                </div>
                            </div>
                            <div class="yd-popup-form__col">
                                <div class="b-float-label">
                                    <input name="PropCity" id="ydFormCity" type="text" value="${data.FIELDS.PropCity}" required="">
                                    <label for="ydFormCity"${data.FIELDS.PropCity ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_CITY')}*</label>
                                </div>
                                <div class="b-float-label">
                                    <textarea name="PropAddress" id="ydFormAddress" required="" rows="10" cols="10">${data.FIELDS.PropAddress}</textarea>
                                    <label for="ydFormAddress"${data.FIELDS.PropAddress ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_ADDRESS')}*</label>
                                </div>
                                <div class="b-float-label">
                                    <textarea name="PropComment" id="ydFormComment">${data.FIELDS.PropComment || ''}</textarea>
                                    <label for="ydFormComment"${data.FIELDS.PropComment ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_COMMENT')}</label>
                                </div>
                            </div>
                        </div>
                    </div>
					<div class="yd-popup-tabs__tabs__item" data-tab="package">${packageTab}</div>
                    <div class="yd-popup-form__submit">
                        <button class="twpx-ui-btn" type="submit">${BX.message('TWINPX_YADELIVERY_SUBMIT')}</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="yd-popup-offers load-circle"></div>
    </div>
	`;
}

function createNewDeliveryPvzHtml(data) {
	let fieldsHidden = '';
	
	if (data.FIELDS_HIDDEN && typeof data.FIELDS_HIDDEN === 'object') {
		Object.entries(data.FIELDS_HIDDEN).forEach((entry) => {
			fieldsHidden += `<input type="hidden" id="${entry[0]}" name="${entry[0]}" value="${entry[1]}">`;
		});
	}
		
	let paymentOptions = '';
	
	if (data.PAYMENT_TYPE && typeof data.PAYMENT_TYPE === 'object') {
		Object.entries(data.PAYMENT_TYPE).forEach((entry) => {
			paymentOptions += `<option value="${entry[0]}"${data.FIELDS && data.FIELDS.PAY_TYPE && data.FIELDS.PAY_TYPE===entry[0] ? 'selected="selected"' : ''}>${entry[1]}</option>`;
		});
	}
	
	const packageTab = packageTabHtml(data);
	
	return `
	<div class="yd-popup-error"></div>
	<div class="yd-popup-body">
		<div class="yd-popup-tabs">
			<div class="yd-popup-tabs__nav">
				<div class="yd-popup-tabs__nav__item yd-popup-tabs__nav__item--active" data-tab="general">${BX.message('TWINPX_MODAL_GENERAL')}</div>
                <div class="yd-popup-tabs__nav__item${(!data.BOXES || !data.BOXES.length || !data.PRODUCTS || !data.PRODUCTS.length) ? ' yd-popup-tabs__nav__item--hidden' : ''}" data-tab="package">${BX.message('TWINPX_MODAL_PACKAGE')}</div>
			</div>
			<div class="yd-popup-tabs__tabs">
				<form action="" novalidate="">
					${fieldsHidden}
					<div class="yd-popup-tabs__tabs__item yd-popup-tabs__tabs__item--active" data-tab="general">
						<div class="yd-popup-form">
							<div class="yd-popup-form__col">
								<div class="b-float-label">
									<input name="ORDER_ID" id="ydFormPvzOrder" type="number" value="${data.FIELDS.ORDER_ID}"${data.FIELDS.ORDER_ID ? ' readonly=""' : ''} required="">
									<label for="ydFormPvzOrder"${data.FIELDS.ORDER_ID ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_ORDER')}*</label>
									<div class="yd-popup-form-fillbutton">${BX.message('TWINPX_YADELIVERY_GETDATA')}</div>
								</div>
								<div class="b-float-label">
									<input name="PropFio" id="ydFormPvzFio" type="text" value="${data.FIELDS.PropFio}" required="">
									<label for="ydFormPvzFio"${data.FIELDS.PropFio ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_FIO')}*</label>
								</div>
								<div class="b-float-label">
									<input name="PropEmail" id="ydFormPvzEmail" type="email" value="${data.FIELDS.PropEmail}">
									<label for="ydFormPvzEmail"${data.FIELDS.PropEmail ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_EMAIL')}</label>
								</div>
								<div class="b-float-label">
									<input name="PropPhone" id="ydFormPvzPhone" type="tel" value="${data.FIELDS.PropPhone}" required="">
									<label for="ydFormPvzPhone"${data.FIELDS.PropPhone ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_PHONE')}*</label>
								</div>
								<div class="b-form-control b-float-label">
									<select name="PAY_TYPE" id="ydFormPay" required="">
										${paymentOptions}
									</select>
								</div>
								<div class="b-float-label">
									<input name="PropPrice" id="ydFormPrice" type="number" min="0" value="${data.FIELDS.PropPrice}" required="">
									<label for="ydFormPrice"${data.FIELDS.PropPrice ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_COST')}</label>
								</div>
							</div>
							<div class="yd-popup-form__col">
								<div class="b-float-label">
									<input name="PropCity" id="ydFormPvzCity" type="text" value="${data.FIELDS.PropCity}" required="">
									<label for="ydFormPvzCity"${data.FIELDS.PropCity ? ' class="active"' : ''}>${BX.message('TWINPX_YADELIVERY_CITY')}*</label>
								</div>
							</div>
						</div>
					</div>
					<div class="yd-popup-tabs__tabs__item" data-tab="package">${packageTab}</div>
					<div class="yd-popup-form__submit">
						<button class="twpx-ui-btn" type="submit">${BX.message('TWINPX_YADELIVERY_SUBMIT')}</button>
					</div>
				</form>
				<div class="yd-popup-map-container"></div>
			</div>
		</div>
	</div>`;
}

function pageScroll(flag) {
  flag
    ? document.querySelector('body').classList.remove('no-scroll')
    : document.querySelector('body').classList.add('no-scroll');
}

function getTitleContent(text) {
  return BX.create('span', {
    html: text,
    props: { className: 'popup-window-titlebar-text' },
  });
}

//sale delivery
function saleDelivery(id, type) {
  function elemLoader(elem, flag) {
    flag
      ? elem.classList.add('load-circle')
      : elem.classList.remove('load-circle');
  }

  window.twinpxYadeliveryFetchURL =
    '/bitrix/tools/twinpx.yadelivery/admin/ajax.php';
  window.ydConfirmer = new BX.PopupWindow('saleDelivery', null, {
    content:
      '<div id="newDeliveryContent" class="yd-popup-content load-circle"></div>',
    titleBar: {
      content: getTitleContent(BX.message('TWINPX_JS_CREATE')),
    },
    ...twinpxYadeliveryPopupSettings,
    events: {
      onPopupShow: function () {
        pageScroll(false);
        $.post(
          window.twinpxYadeliveryFetchURL,
          {
            action: 'saleNewDelivery',
            itemID: id,
            type: type,
          },
          function (data) {
            ydNewDeliveryContent =
              document.getElementById(`newDeliveryContent`);
            ydNewDeliveryContent.innerHTML = data;
            elemLoader(ydNewDeliveryContent, false);
            window.ydConfirmer.adjustPosition();
            /*newDeliveryPopupOnload();*/

            //fill button
            twinpxYadeliveryFillbutton(
              ydNewDeliveryContent.querySelector('form')
            );

            //paysystem select
            twinpxYadeliveryPaysystemSelect(ydForm);

            //float label input
            ydNewDeliveryContent
              .querySelectorAll('.b-float-label input, .b-float-label textarea')
              .forEach((control) => {
                let item = control.closest('.b-float-label'),
                  label = item.querySelector('label');

                if (control.value.trim() !== '') {
                  label.classList.add('active');
                }

                control.addEventListener('blur', () => {
                  if (control.value.trim() !== '') {
                    label.classList.add('active');
                  } else {
                    label.classList.remove('active');
                  }
                });

                control.addEventListener('keyup', () => {
                  if (item.classList.contains('invalid')) {
                    validate(item, control);
                  }
                });
              });

            function validate(item, control) {
              let regExp = {
                email: /^([^@\s]+)@((?:[-a-z0-9]+\.)+[a-z]{2,})$/i,
                //tel: /^[\+][0-9]?[-\s\.]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/im,//+9 (999) 999 9999
              };

              //required
              if (
                control.getAttribute('required') === '' &&
                control.value.trim() !== ''
              ) {
                item.classList.remove('invalid');
              }

              Object.keys(regExp).forEach((key) => {
                if (control.getAttribute('type') === key) {
                  if (
                    (control.value.trim() !== '' &&
                      regExp[key].test(control.value)) ||
                    (control.getAttribute('required') !== '' &&
                      control.value.trim() === '')
                  ) {
                    item.classList.remove('invalid');
                  }
                }
              });
            }
          }
        );
      },
      onPopupClose: function (Confirmer) {
        Confirmer.destroy();
        pageScroll(true);
      },
    },
    buttons: [],
  });
  window.ydConfirmer.show();
}

$(document).on('submit', '#saleNewDelivery', function (e) {
  e.preventDefault();
  action = $(this).data('action');

  function elemLoader(elem, flag) {
    flag
      ? elem.classList.add('load-circle')
      : elem.classList.remove('load-circle');
  }

  $.post(
    window.twinpxYadeliveryFetchURL,
    {
      action: action,
      form: $(this).serialize(),
    },
    function (data) {
      node = document.getElementById(`newDeliveryContent`);
      node.innerHTML = data;
      elemLoader(node, false);
      window.ydConfirmer.adjustPosition(); //������������ ���������
    }
  );
  return false;
});

//self points
function setPlatformId(inputId) {
  let ydContent,
    ydPopupContainer,
    ydPopupList,
    ydPopupWrapper,
    map,
    objectManager,
    bounds,
    firstGeoObjectCoords,
    regionName = 'Moscow',
    pointsArray,
    pointsNodesArray = {},
    newBounds = [],
    content = `<div id="setPlatformContentPvz" class="yd-popup-content load-circle">
      <div class="yd-popup-error"></div>
      <div class="yd-popup-body yd-popup-body--result">
        <div class="yd-popup-map-container">
          <div class="yd-popup-container yd-popup--map">
            <div id="ydPopupMap" class="yd-popup-map load-circle"></div>
            <div class="yd-popup-list">
              <div class="yd-popup-list-wrapper load-circle"></div>
            </div>
          </div>
        </div>
      </div>
    </div>`;

  setPlatformPvz();

  ydContent = document.querySelector('#setPlatformPvz .yd-popup-content');
  ydPopupContainer = ydContent.querySelector('.yd-popup-container');
  ydPopupList = ydPopupContainer.querySelector('.yd-popup-list');
  ydPopupWrapper = ydPopupList.querySelector('.yd-popup-list-wrapper');

  function setPlatformPvz() {
    window.ydSetPlatformrPvz = new BX.PopupWindow('setPlatformPvz', null, {
      content: content,
      titleBar: {
        content: getTitleContent(BX.message('TWINPX_JS_SELFPLATFORM')),
      },
      ...twinpxYadeliveryPopupSettings,
      events: {
        onPopupShow: function () {
          pageScroll(false);
          onPopupShow();
          window.ydSetPlatformrPvz.adjustPosition();
        },
        onPopupClose: function (Confirmer) {
          Confirmer.destroy();
          pageScroll(true);
        },
      },
    });
    window.ydSetPlatformrPvz.show();
  }

  function onPopupShow() {
    pointsArray = [];

    //ymaps
    if (window.ymaps && window.ymaps.ready) {
      ymaps.ready(() => {
        //geo code
        const myGeocoder = ymaps.geocode(regionName, {
          results: 1,
        });

        myGeocoder.then((res) => {
          // first result, its coords and bounds
          let firstGeoObject = res.geoObjects.get(0);
          firstGeoObjectCoords = firstGeoObject.geometry.getCoordinates();
          bounds = firstGeoObject.properties.get('boundedBy');
          newBounds = bounds;

          map = new ymaps.Map(
            'ydPopupMap',
            {
              center: firstGeoObjectCoords,
              zoom: 9,
              controls: ['searchControl', 'zoomControl'],
            },
            {
              suppressMapOpenBlock: true,
            }
          );

          let customBalloonContentLayout =
            ymaps.templateLayoutFactory.createClass(
              `<div class="yd-popup-balloon-content">${BX.message(
                'TWINPX_JS_MULTIPLE_POINTS'
              )}</div>`
            );

          objectManager = new ymaps.ObjectManager({
            clusterize: true,
            clusterBalloonContentLayout: customBalloonContentLayout,
          });

          objectManager.objects.options.set('iconLayout', 'default#image');
          objectManager.objects.options.set(
            'iconImageHref',
            '/bitrix/images/twinpx.yadelivery/yandexPoint.svg'
          );
          objectManager.objects.options.set('iconImageSize', [32, 42]);
          objectManager.objects.options.set('iconImageOffset', [-16, -42]);
          objectManager.clusters.options.set(
            'preset',
            'islands#blackClusterIcons'
          );
          objectManager.objects.events.add(['click'], onObjectEvent);
          //objectManager.clusters.events.add(['click'], onClusterEvent);

          let firstBound = true;

          if (map) {
            //add object manager
            map.geoObjects.add(objectManager);

            //remove preloader
            elemLoader(document.querySelector('#ydPopupMap'), false);
            //map bounds
            map.setBounds(bounds, {
              checkZoomRange: true,
            });
            //events
            map.events.add('boundschange', onBoundsChange);
          }

          function onBoundsChange(e) {
            newBounds = e ? e.get('newBounds') : newBounds;

            if (firstBound) {
              firstBound = false;
              return;
            }

            //wrapper sorted mode
            ydPopupWrapper.classList.add('yd-popup-list-wrapper--sorted');

            //clear sorted pvz
            for (let key in pointsNodesArray) {
              if (pointsNodesArray[key]['sorted'] === true) {
                pointsNodesArray[key]['node'].classList.remove(
                  'yd-popup-list__item--sorted'
                );
              }
            }

            //items array
            let arr = pointsArray.filter((point) => {
              return (
                point.coords[0] > newBounds[0][0] &&
                point.coords[0] < newBounds[1][0] &&
                point.coords[1] > newBounds[0][1] &&
                point.coords[1] < newBounds[1][1]
              );
            });

            //set items sorted
            arr.forEach((point) => {
              let sortedItem = pointsNodesArray[point.id]['node'];
              pointsNodesArray[point.id]['sorted'] = true;
              if (sortedItem) {
                sortedItem.classList.add('yd-popup-list__item--sorted');
              }
            });
          }

          //send to the server
          (async () => {
            //get offices
            let formData = new FormData();
            formData.set('action', 'getReception');

            let controller = new AbortController();
            let response;

            setTimeout(() => {
              if (!response) {
                controller.abort();
              }
            }, 20000);

            try {
              let response = await fetch(window.twinpxYadeliveryFetchURL, {
                method: 'POST',
                body: formData,
              });
              let result = await response.json();

              //remove preloader
              elemLoader(ydPopupWrapper, false);

              if (result && result.STATUS === 'Y' && result.POINTS) {
                //fill pointsArray
                pointsArray = result.POINTS;

                //list
                let pointsFlag,
                  objectsArray = [],
                  featureOptions = {};

                result.POINTS.forEach(
                  ({ id, title, schedule, address, coords }) => {
                    if (!id) return;

                    pointsFlag = true;

                    featureOptions = {};

                    //placemark
                    objectsArray.push({
                      type: 'Feature',
                      id: id,
                      geometry: {
                        type: 'Point',
                        coordinates: coords,
                      },
                      options: featureOptions,
                    });

                    //list
                    let item = document.createElement('div');
                    item.className = 'yd-popup-list__item';
                    item.setAttribute('data-id', id);

                    item.innerHTML = `
                        <div class="yd-popup-list__title">${title}</div>
                        <div class="yd-popup-list__text">
                          ${schedule}<br>
                          ${address}
                        </div>
                        <div class="twpx-ui-btn">${BX.message(
                          'TWINPX_JS_SELECT'
                        )}</div>
                      `;

                    item.addEventListener('click', (e) => {
                      if (e.target.classList.contains('twpx-ui-btn')) {
                        //set id value
                        document.getElementById(inputId).value = id;
                        window.ydSetPlatformrPvz.destroy();
                        pageScroll(true);
                      } else {
                        clickPlacemark(map, coords);
                      }
                    });

                    ydPopupWrapper.appendChild(item);

                    //push to nodes array
                    pointsNodesArray[id] = {
                      node: item,
                      sorted: false,
                    };
                  }
                );

                objectManager.add(objectsArray);

                if (!pointsFlag) {
                  pointsError();
                }

                if (
                  ydPopupWrapper.classList.contains(
                    'yd-popup-list-wrapper--sorted'
                  )
                ) {
                  //if the map was moved while offices were loading
                  onBoundsChange();
                }

                //map bounds
                if (map) {
                  centerCoords = map.getCenter();
                }
              } else {
                pointsError(result.ERRORS);
              }
            } catch (err) {
              pointsError();
            }
          })();
        });
      });
    }
  }

  function elemLoader(elem, flag) {
    flag
      ? elem.classList.add('load-circle')
      : elem.classList.remove('load-circle');
  }

  function pointsError(error) {
    ydPopupWrapper.innerHTML = `<div class="yd-popup-error__message"><i style="background-image: url(/bitrix/images/twinpx.yadelivery/danger.svg)"></i>${error}</div>`;
  }

  function onObjectEvent(e) {
    let id = e.get('objectId');

    let pointObject = pointsArray.find((p) => {
      return p.id === id;
    });

    clickPlacemark(map, pointObject.coords);
  }

  async function clickPlacemark(map, coords) {
    map.panTo(coords).then(() => {
      map.setZoom(16);
    });
  }
}
