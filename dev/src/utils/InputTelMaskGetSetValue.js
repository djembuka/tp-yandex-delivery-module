export //input tel in popups
class InputTelMaskGetSetValue {
  constructor(input) {
    this.input = input;
    this.instance = true;
    this.input.instance = this;
    this.init();
  }

  init() {
    this.input.addEventListener('focus', () => {
      this.focus();
    });
    this.input.addEventListener('blur', () => {
      this.blur();
    });
    this.input.addEventListener('keydown', (e) => this.keydown(e));
  }

  focus() {
    if (this.input.value === '') {
      this.input.value = '+7 (';
    }
  }

  blur() {
    if (this.input.value === '+7 (') {
      this.input.value = '';
    }
  }

  keydown(e) {
    let key = e.key;
    let not = key.replace(/([0-9])/, 1);

    if (not == 1) {
      if (this.input.value.length < 4 || this.input.value === '') {
        this.input.value = '+7 (';
      }
      if (this.input.value.length === 7) {
        this.input.value = this.input.value + ') ';
      }
      if (this.input.value.length === 12) {
        this.input.value = this.input.value + '-';
      }
      if (this.input.value.length === 15) {
        this.input.value = this.input.value + '-';
      }
      if (this.input.value.length >= 18) {
        this.input.value = this.input.value.substring(0, 17);
      }
    } else if ('Backspace' !== not && 'Tab' !== not) {
      e.preventDefault();
    }
  }

  get val() {
    let phone = this.input.value.replace(/\D/g, '').substr(0, 11);
    let first = phone.substr(0, 1);
    if (phone.length > 0 && phone.length < 11) {
      phone = `${first !== '7' ? '7' : ''}${phone}`;
    } else if (first && first !== '7') {
      phone = `7${phone.substr(1)}`;
    }

    return phone;
  }

  set val(value) {
    let phone = value.replace(/\D/g, '').substr(0, 11);
    let first = phone.substr(0, 1);
    if (phone.length > 0 && phone.length < 11) {
      phone = `${first !== '7' ? '7' : ''}${phone}`;
    } else if (first && first !== '7') {
      phone = `7${phone.substr(1)}`;
    }

    let result = '';

    if (phone.substr(0, 1)) {
      result += `+${phone.substr(0, 1)}`;
    }
    if (phone.substr(1, 1)) {
      result += ' (';
    }
    result += phone.substr(1, 3);
    if (phone.substr(3, 1)) {
      result += ') ';
    }
    result += phone.substr(4, 3);
    if (phone.substr(7, 1)) {
      result += '-';
    }
    result += phone.substr(7, 2);
    if (phone.substr(9, 1)) {
      result += '-';
    }
    result += phone.substr(9, 2);

    this.input.value = result;
  }
}