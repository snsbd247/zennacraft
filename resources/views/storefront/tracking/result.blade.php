@extends('layouts.app')
@section('title', 'Tracking '.$order->order_number)
@section('content')
@include('storefront.tracking._result', ['order' => $order, 'timeline' => $timeline ?? collect(), 'shipment' => $shipment ?? $order->shipment])
@endsection
