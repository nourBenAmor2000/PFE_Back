@extends('attribute::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('attribute.name') !!}</p>
@endsection
