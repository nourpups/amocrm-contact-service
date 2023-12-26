@extends('layouts.layout')

@section('title', 'Кантакты сахраняттъ')

@section('section')
    <form id="contact-form" class="container w-50" action="{{route('contacts.store')}}" onsubmit="sendData(event)" method="POST">
        @csrf
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <h1 class="text-end">Создать контакт</h1>
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
            // Получаем данные формы
            let formData = new FormData(document.getElementById('contact-form'));

            fetch('{{ route("contacts.store") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{csrf_token()}}',
                    'Accept': 'application/json'
                },
                body: formData,
            })
                .then(response => response.json())
                .then(data => {
                    location.reload()
                })
                .catch(error => {
                    location.reload()
                    // Обрабатываем ошибку
                    console.error('Error:', error);

                    // Отображаем ошибки валидации (если они есть)
                    if (error.response && error.response.status === 422) {
                        error.response.json().then(errors => {
                            // Отображаем ошибки валидации в нужном месте вашего интерфейса
                            console.log(errors);
                            // Например, можно обновить DOM с ошибками в форме
                            updateFormErrors(errors.errors);
                        });
                    }
                });
        }

        function updateFormErrors(errors) {
            // Определите, как вы хотите отобразить ошибки в форме
            // Например, вы можете добавить сообщения об ошибках рядом с соответствующими полями
            for (let fieldName in errors) {
                let errorMessages = errors[fieldName];
                let inputElement = document.querySelector('[name="' + fieldName + '"]');
                let errorContainer = document.createElement('div');
                errorContainer.className = 'text-danger';
                errorContainer.innerHTML = errorMessages.join('<br>');
                inputElement.parentNode.appendChild(errorContainer);
            }
        }
    </script>
@endsection
