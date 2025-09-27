@extends('client::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('client.name') !!}</p>
@endsection
