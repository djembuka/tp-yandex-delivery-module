export function pageScroll(flag) {
  flag
    ? document.querySelector('body').classList.remove('no-scroll')
    : document.querySelector('body').classList.add('no-scroll');
}

export function twinpxYadeliverySerializeForm(form) {
  const obj = Object.fromEntries(new FormData(form));

  let result = '';

  Object.keys(obj).forEach((key) => {
    result += `&${key}=${obj[key]}`;
  });

  return result.substring(1);
}