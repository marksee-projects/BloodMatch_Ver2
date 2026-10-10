export function registrationPayload(form, location) {
  return {
    first_name: form.first_name.trim(),
    middle_name: form.middle_name.trim() || null,
    last_name: form.last_name.trim(),
    email: form.email.trim().toLowerCase(),
    password: form.password,
    chapter_id: Number(form.chapter_id),
    date_of_birth: form.date_of_birth,
    blood_type: form.blood_type || null,
    phone: form.phone.trim() || null,
    ...(location.location_id ? location : {}),
    privacy_acknowledged: true
  }
}

export function registrationFormData(payload, idFile) {
  const data = new FormData()
  for (const [key, value] of Object.entries(payload)) {
    if (value !== null && value !== undefined) data.append(key, String(value))
  }
  data.append('national_id', idFile)
  return data
}
