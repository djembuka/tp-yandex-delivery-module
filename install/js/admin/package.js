(() => {
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
        validateForm();
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

        validateForm();
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
(() => {
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

        validateForm();
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
  })();

})();