@extends('layouts.layout')

@section('title', 'Кантакты сахраняттъ')

@section('section')

    <form id="contact-form" class="container w-50" action="{{route('contacts.store')}}" onsubmit="sendData(event)" method="POST">
        <h1 class="text-end">Создать контакт</h1>
        <div id="validationErrors" class="rounded p-3 my-3"
             style="display: none; background-color: #f8d7da; border-color: #f5c6cb;color: #721c24"></div>
        <div class="mb-3">
            <label class="form-label">Имя</label>
            <input type="text" name="first_name" class="form-control" placeholder="Ложкабек" />
        </div>
        <div class="mb-3">
            <label class="form-label">Фамилия</label>
            <input type="text" name="last_name" class="form-control" placeholder="Тарелков" />
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="custom_fields_values[email]" class="form-control" placeholder="name@example.com" />
        </div>
        <div class="mb-3">
            <label class="form-label">Телефон</label>
            <input type="text" name="custom_fields_values[phone]" class="form-control" placeholder="+998 123 45 67" />
        </div>
        <div class="mb-3">
            <label class="form-label">Возраст</label>
            <input type="number" name="custom_fields_values[age]" class="form-control" placeholder="106" />
        </div>
        <div class="mb-3">
            <label class="form-label">Пол</label>
            <select class="form-select" name="custom_fields_values[gender]" aria-label="Default select example">
                @foreach($genders as $gender)
                    <option value="{{$gender}}">{{$gender}}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary">Создать контакт</button>
    </form>
@endsection

@section('js')
    <script>
        function sendData(event) {
            event.preventDefault();
            let formData = new FormData(document.getElementById('contact-form'));

            fetch('{{ route("contacts.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{csrf_token()}}',
                    'Accept': 'application/json',
                },
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                if(data.success) {
                    const contactForm = document.getElementById('contact-form')
                    contactForm.reset();

                    const successMessageElement = document.getElementById('successMessage');
                    successMessageElement.innerHTML = data.success
                    successMessageElement.style.display = 'block';
                    successMessageElement.classList.add('show', 'text-center');
                }
                if(data.errors) {
                    const validationErrorsElement = document.getElementById('validationErrors');
                    validationErrorsElement.innerHTML = '';

                    Object.keys(data.errors).forEach(fieldName => {
                        const errorMessage = data.errors[fieldName][0];
                        displayValidationError(validationErrorsElement, errorMessage);
                    });

                    validationErrorsElement.style.display = 'block';
                }

                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function displayValidationError(element, errorMessage) {
            // Создаем новый элемент для отображения ошибки
            const errorElement = document.createElement('p');
            errorElement.textContent = errorMessage;

            // Добавляем элемент с ошибкой в элемент для отображения ошибок
            element.appendChild(errorElement);
        }
    </script>
@endsection
