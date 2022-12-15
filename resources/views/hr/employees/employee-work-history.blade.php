@extends('layouts.app')
@section('content')
<div class="container" >
    <div class="ml-6"><h1>Work History</h1></div>
    <div>
  <table class="table table-bordered table-striped   bg-white text-dark w-full">
  <thead class="bg-secondary text-white text-center  align-middle "style="width:1%">
    <tr>
      <th class="align-middle "style="width:1%"  rowspan="2" scope="col">Projects</th>
      <th class="align-middle" style="width:1%"  rowspan="2" scope="col">Clients</th>
      <th class="align-middle"  colspan="4" scope="col">TechStacks</th>
    </tr>
    <tr>
      <th>Language</th>
      <th>Framework</th>
      <th>Database</th>
      <th>Hosting</th>
    </tr>
  </thead>
  <tbody>
    @foreach($employeesDetails as $details)
    <tr>
      <td class="text-center "><a href="{{route('project.edit', $details->project  )}}">{{ $details->project['name'] }}</a></td>   
      <td class="text-center ">{{ $details->project->client['name'] }}</td>
        @foreach ($allTechStacks[$details["project_id"]] as $TechStacks )
           @if (count($TechStacks)==0)
           <td class="text-center " colspan="4">Not Avaliable</td>
           @else
              @foreach($TechStacks as $tech)
                @if ($tech['value'])
                  <td class="text-center ">{{ $tech['value'] }}</td>
                @else
                <td class="text-center">Not Avaliable</td>
                @endif
              @endforeach
            @endif
        @endforeach
    </tr>
    @endforeach
  </tbody>
</table>	   
</div>
</div>   
@endsection
