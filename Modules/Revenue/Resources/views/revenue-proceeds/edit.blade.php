<div class="modal fade text-left"  tabindex="-1" role="dialog" id="modalEdit" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Revenue</h3>
                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form action="{{route('revenue.proceeds.update','id')}}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-row">
                            <input value="{{route('revenue.proceeds.update','id')}}" type="hidden" class="hidden" aria-hidden="true" name="routePlaceHolder">
                            <div class="form-group ml-6 col-md-5">
                                <label for="" class="field-required">Name</label>
                                <input type="text" class="form-control" required name="name">
                            </div>
                            <div class="form-group offset-md-1 mb-0">
                                <label for="" class="field-required">Currency</label>
                                <div class="input-group">
                                    <div class=" input-group-prepend">
                                        <select name="currency" name="currency" class="input-group-text" required>
                                            <option class="disabled"></option>
                                            <option value="USD">USD</option>
                                            <option value="INR">INR</option>
                                            <option value="CAD">CAD</option> 
                                        </select>
                                    </div>
                                  <input type="number" class="form-control" name="amount">
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group ml-6 col-md-5">
                                <label for="" class="field-required">Category</label>
                                <select type="text" class="form-control" name="category" required>
                                    <option class="disabled">Select category</option>
                                    <option value="domestic">Domestic</option>
                                    <option value="export">Export</option>
                                    <option value="recieved-commission">Recieved Commission</option>
                                    <option value="cash-back">Cash Back</option>
                                    <option value="discount-recieved">Recieved Discount</option>
                                    <option value="interest-on-fd">Interest On FD</option>
                                    <option value="foreign-exchange-loss">Foreign Exchange Loss</option>
                                </select>
                            </div> 
                            <div class="form-group offset-md-1 col-md-5">
                                <label for="" class="field-required">Date of Recieved</label>
                                <input type="date" class="form-control" required name="recieved_at" >
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group ml-6 col-md-7">
                                <label for="">Note</label>
                                <textarea type="text" class="form-control" rows="3" name="notes"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="save-btn-action">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>