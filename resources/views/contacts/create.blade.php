@extends('layouts.layout')

@section('title', 'Кантакты сахраняттъ')

@section('section')
    <form class="container w-50" action="{{route('contacts.store')}}" method="POST">
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
