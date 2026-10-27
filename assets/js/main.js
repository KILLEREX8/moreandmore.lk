(() => {
  const toggle = document.querySelector('.menu-toggle');
  const navigation = document.querySelector('.main-nav');
  if (toggle && navigation) {
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') === 'true';
      toggle.setAttribute('aria-expanded', String(!open));
      navigation.classList.toggle('is-open', !open);
    });
  }

  document.querySelectorAll('[data-submit-on-change]').forEach((control) => {
    control.addEventListener('change', () => control.form?.requestSubmit());
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Continue?')) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-print-invoice]').forEach((button) => {
    button.addEventListener('click', () => window.print());
  });

  document.querySelectorAll('form[data-disable-on-submit]').forEach((form) => {
    form.addEventListener('submit', () => {
      const submit = form.querySelector('button[type="submit"], button:not([type])');
      if (submit) {
        submit.disabled = true;
        submit.dataset.originalText = submit.textContent;
        submit.textContent = 'Please wait…';
      }
    });
  });

  document.querySelectorAll('.thumbnail-button').forEach((button) => {
    button.addEventListener('click', () => {
      const image = document.querySelector('#detail-image');
      if (!image) return;
      image.src = button.dataset.image || '';
      image.alt = button.dataset.alt || '';
      document.querySelectorAll('.thumbnail-button').forEach((item) => item.classList.toggle('is-selected', item === button));
    });
  });

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll('.product-card, .category-card, .promise-copy').forEach((item) => {
    item.classList.add('reveal');
    observer.observe(item);
  });
})();
