const profileSelector = '[data-employee-profile]';
let requestInProgress = false;

const element = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    node.textContent = text;

    return node;
};

const setBusy = (profile, busy) => {
    requestInProgress = busy;
    profile.setAttribute('aria-busy', String(busy));
    profile.querySelectorAll('button, select').forEach((control) => {
        if (busy) {
            control.dataset.previouslyDisabled = String(control.disabled);
            control.disabled = true;
        } else if (control.dataset.previouslyDisabled !== undefined) {
            control.disabled = control.dataset.previouslyDisabled === 'true';
            delete control.dataset.previouslyDisabled;
        }
    });
};

const showError = (profile, message) => {
    const error = profile.querySelector('[data-profile-error]');
    error.textContent = message;
    error.hidden = false;
    error.focus({ preventScroll: true });
    error.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

const postForm = async (form) => {
    const response = await fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: new FormData(form),
    });
    const data = await response.json().catch(() => null);

    if (!response.ok) {
        if (response.status === 419) {
            throw new Error('Сессия истекла. Обновите страницу и повторите действие.');
        }
        if (response.status === 403) {
            throw new Error('Нет доступа к этому профилю. Проверьте выбранную роль.');
        }
        if (response.status === 422) {
            throw new Error(Object.values(data?.errors ?? {}).flat().join(' ') || 'Проверьте выбранную активность.');
        }

        throw new Error('Не удалось сохранить действие. Повторите попытку чуть позже.');
    }
    if (!data) {
        throw new Error('Сервер вернул неожиданный ответ. Обновите страницу.');
    }

    return data;
};

const renderRecommendations = (profile, recommendations) => {
    const list = profile.querySelector('[data-recommendations-list]');
    const template = profile.querySelector('[data-recommendation-template]');
    const factorLabels = JSON.parse(profile.dataset.factorLabels);
    const typeLabels = JSON.parse(profile.dataset.typeLabels);
    list.replaceChildren();

    if (recommendations.length === 0) {
        const empty = element('div', 'col-span-full rounded-2xl border border-dashed border-slate-300 bg-white/50 px-6 py-9 text-center', '');
        empty.append(
            element('p', 'font-medium text-slate-700', 'Подходящих активностей пока нет'),
            element('p', 'mt-2 text-sm text-slate-500', 'Доступные события уже завершены, требуют других навыков или не приближают к карьерной цели. HR может помочь подобрать следующий шаг.'),
        );
        list.append(empty);

        return;
    }

    recommendations.forEach((recommendation) => {
        const card = template.content.firstElementChild.cloneNode(true);
        card.dataset.eventId = recommendation.event_id;
        card.querySelector('[data-card-rank]').textContent = recommendation.rank === 1 ? 'Рекомендуем начать' : `Вариант ${recommendation.rank}`;
        card.querySelector('[data-card-type]').textContent = typeLabels[recommendation.type] ?? recommendation.type;
        card.querySelector('[data-card-title]').textContent = recommendation.title;
        card.querySelector('[data-card-rationale]').textContent = recommendation.rationale;
        card.querySelector('[data-card-source]').textContent = recommendation.source === 'llm' ? 'AI-обоснование · llm' : 'Правила подбора · fallback';
        card.querySelector('[data-card-score]').textContent = `Рейтинг ${Number(recommendation.score).toFixed(1)}`;
        card.querySelector('input[name="event_id"]').value = recommendation.event_id;

        recommendation.factors.forEach((factor) => {
            card.querySelector('[data-card-factors]').append(element('span', 'rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800', factorLabels[factor] ?? factor));
        });
        list.append(card);
    });
};

const refreshProfile = async (profile) => {
    const response = await fetch(profile.dataset.profileUrl, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'text/html' },
    });

    if (!response.ok) {
        throw new Error('Не удалось обновить профиль.');
    }

    const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
    const updatedProfile = documentFragment.querySelector(profileSelector);

    if (!updatedProfile) {
        throw new Error('Не удалось обновить профиль.');
    }

    profile.replaceWith(updatedProfile);

    return updatedProfile;
};

const renderCompletion = (profile, data, beforeReadiness, skillNames) => {
    const result = profile.querySelector('[data-completion-result]');
    result.replaceChildren(element('h2', 'text-lg font-semibold text-emerald-950', 'Активность завершена. Прогресс сохранён!'));
    const deltas = element('div', 'mt-4 flex flex-wrap gap-2', '');

    profile.querySelectorAll('[data-skill-id]').forEach((skill) => {
        skillNames[skill.dataset.skillId] = skill.dataset.skillName;
    });
    data.skill_deltas.forEach((delta) => {
        const gain = delta.to - delta.from;
        deltas.append(element('span', 'rounded-lg border border-emerald-200 bg-white px-3 py-2 text-sm text-emerald-900', `${skillNames[delta.skill_id] ?? delta.skill_id}: ${delta.from} → ${delta.to}${gain > 0 ? ` (+${gain})` : ' · максимум активности'}`));
    });
    result.append(deltas);

    if (data.skill_deltas.length === 0) {
        result.append(element('p', 'mt-2 text-sm text-emerald-800', 'Активность добавлена в историю. Изменений уровней навыков нет.'));
    }

    const readiness = data.grade_readiness;
    const percent = readiness.total > 0 ? Math.round(readiness.covered / readiness.total * 100) : 0;
    result.append(element('p', 'mt-4 text-sm text-emerald-900', readiness.total > 0
        ? `Готовность к ${readiness.grade}: ${beforeReadiness} → ${percent}%. Закрыто ${readiness.covered} из ${readiness.total} требований.`
        : 'Требования следующего грейда пока не заданы.'));
    result.append(element('p', 'mt-2 text-xs text-emerald-700', 'Получите новые рекомендации с учётом обновлённых навыков.'));
    result.hidden = false;
    result.focus({ preventScroll: true });
    result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

document.addEventListener('submit', async (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-recommendation-form], [data-completion-form]')) {
        return;
    }
    event.preventDefault();

    if (requestInProgress) {
        return;
    }

    let profile = form.closest(profileSelector);
    const isRecommendation = form.matches('[data-recommendation-form]');
    const loading = profile.querySelector('[data-recommendation-loading]');
    const list = profile.querySelector('[data-recommendations-list]');
    const status = profile.querySelector('[data-recommendation-status]');
    const button = form.querySelector('button[type="submit"]');
    const originalButtonText = button.textContent;
    const beforeReadiness = profile.querySelector('[data-readiness-percent]').textContent.replace('%', '').trim();
    const skillNames = Object.fromEntries([...profile.querySelectorAll('[data-skill-id]')].map((skill) => [skill.dataset.skillId, skill.dataset.skillName]));

    // Capture selected values before controls are disabled; disabled controls are omitted by FormData.
    const request = postForm(form);
    setBusy(profile, true);
    profile.querySelector('[data-profile-error]').hidden = true;
    button.textContent = isRecommendation ? 'Подбираем активности…' : 'Сохраняем прогресс…';

    if (isRecommendation) {
        loading.hidden = false;
        list.hidden = true;
        status.textContent = 'Анализируем профиль, карьерную цель и историю участия…';
    }

    try {
        const data = await request;

        if (isRecommendation) {
            renderRecommendations(profile, data.recommendations);
            status.textContent = data.recommendations.length > 0 ? `Подобрано активностей: ${data.recommendations.length}.` : 'Подбор завершён.';
        } else {
            let refreshFailed = false;
            try {
                profile = await refreshProfile(profile);
            } catch {
                refreshFailed = true;
                profile.querySelectorAll('[data-completion-button]').forEach((control) => {
                    control.dataset.previouslyDisabled = 'true';
                });
            }
            renderCompletion(profile, data, beforeReadiness, skillNames);
            if (refreshFailed) {
                showError(profile, 'Завершение сохранено, но профиль не обновился. Обновите страницу, чтобы увидеть актуальные навыки и историю.');
            }
        }
    } catch (error) {
        if (isRecommendation) {
            status.textContent = '';
        }
        showError(profile, error instanceof TypeError ? 'Нет связи с сервером. Проверьте подключение и повторите попытку.' : error.message);
    } finally {
        setBusy(profile, false);
        loading.hidden = true;
        list.hidden = false;
        button.textContent = originalButtonText;
    }
});
